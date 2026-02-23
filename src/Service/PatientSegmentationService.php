<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\MedicalDossier;
use App\Entity\User;
use App\Repository\ActiviteRepository;
use App\Repository\MedicalDossierRepository;
use App\Repository\SecurityAlertRepository;
use App\Repository\UserRepository;

class PatientSegmentationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MedicalDossierRepository $medicalDossierRepository,
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly ActiviteRepository $activiteRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSegments(int $days = 30): array
    {
        $days = max(7, min(120, $days));
        $patients = $this->userRepository->findPatientsForFollowup();
        if ($patients === []) {
            return [
                'days' => $days,
                'total_patients' => 0,
                'profile_groups' => [],
                'risk_groups' => [],
                'behavior_groups' => [],
                'patient_rows' => [],
            ];
        }

        $patientIds = array_values(array_filter(array_map(
            static fn (User $u): int => (int) ($u->getId() ?? 0),
            $patients
        ), static fn (int $id): bool => $id > 0));

        $dossiers = $this->medicalDossierRepository->findByUsers($patients);
        $dossierByUserId = [];
        foreach ($dossiers as $dossier) {
            if (!$dossier instanceof MedicalDossier) {
                continue;
            }
            $userId = (int) ($dossier->getUser()?->getId() ?? 0);
            if ($userId > 0) {
                $dossierByUserId[$userId] = $dossier;
            }
        }

        $securityByUserId = $this->securityAlertRepository->findOpenRiskStatsForUsers($patientIds);
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
        $activityByUserId = $this->activiteRepository->findActivityCountForUsersSince($patientIds, $since);
        $dropWindow = $days >= 60 ? 14 : 7;
        $dropByUserId = $this->activiteRepository->findActivityDropStatsForUsers($patientIds, $dropWindow);

        $profileGroups = [];
        $riskGroups = [
            'critical' => ['label' => 'Critique', 'count' => 0, 'patients' => []],
            'high' => ['label' => 'Eleve', 'count' => 0, 'patients' => []],
            'medium' => ['label' => 'Moyen', 'count' => 0, 'patients' => []],
            'low' => ['label' => 'Faible', 'count' => 0, 'patients' => []],
        ];
        $behaviorGroups = [
            'comportement_suspect' => ['label' => 'Comportement suspect', 'count' => 0, 'patients' => []],
            'baisse_forte_activite' => ['label' => 'Baisse forte activite', 'count' => 0, 'patients' => []],
            'tres_actif' => ['label' => 'Tres actif', 'count' => 0, 'patients' => []],
            'stable' => ['label' => 'Stable', 'count' => 0, 'patients' => []],
        ];
        $patientRows = [];

        foreach ($patients as $patient) {
            $userId = (int) ($patient->getId() ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $dossier = $dossierByUserId[$userId] ?? null;
            $disease = $this->resolveDisease($patient, $dossier);
            $gender = $this->resolveGender($patient);

            $security = $securityByUserId[$userId] ?? ['open_alerts' => 0, 'max_risk' => 0, 'avg_risk' => 0.0];
            $activityCount = (int) ($activityByUserId[$userId] ?? 0);
            $dropStats = $dropByUserId[$userId] ?? ['current_count' => 0, 'previous_count' => 0, 'drop_count' => 0, 'drop_percent' => 0.0];

            $medicalRiskScore = $this->medicalRiskScoreFromDossier($dossier);
            $securityMaxRisk = (int) ($security['max_risk'] ?? 0);
            $behaviorBoost = $this->behaviorRiskBoost($dropStats, $activityCount, (int) ($security['open_alerts'] ?? 0));
            $combinedRiskScore = min(100, max($medicalRiskScore, $securityMaxRisk) + $behaviorBoost);
            $riskLevel = $this->riskLevelFromScore($combinedRiskScore);
            $behavior = $this->behaviorLabel($dropStats, $activityCount, (int) ($security['open_alerts'] ?? 0), $securityMaxRisk);

            $profileCode = sprintf('%s|%s', $disease, $gender);
            if (!isset($profileGroups[$profileCode])) {
                $profileGroups[$profileCode] = [
                    'code' => $profileCode,
                    'label' => sprintf('%s / %s', $this->formatDisease($disease), $gender),
                    'disease' => $disease,
                    'gender' => $gender,
                    'count' => 0,
                    'avg_risk_score' => 0.0,
                    'patients' => [],
                ];
            }

            $row = [
                'user_id' => $userId,
                'full_name' => $patient->getFullName(),
                'email' => (string) ($patient->getEmail() ?? ''),
                'disease' => $disease,
                'gender' => $gender,
                'risk_level' => $riskLevel,
                'risk_score' => $combinedRiskScore,
                'open_alerts' => (int) ($security['open_alerts'] ?? 0),
                'max_security_risk' => $securityMaxRisk,
                'activity_count' => $activityCount,
                'activity_drop_percent' => (float) ($dropStats['drop_percent'] ?? 0.0),
                'behavior' => $behavior,
            ];

            $profileGroups[$profileCode]['count']++;
            $profileGroups[$profileCode]['avg_risk_score'] += $combinedRiskScore;
            $profileGroups[$profileCode]['patients'][] = $row;

            $riskGroups[$riskLevel]['count']++;
            $riskGroups[$riskLevel]['patients'][] = $row;

            $behaviorGroups[$behavior]['count']++;
            $behaviorGroups[$behavior]['patients'][] = $row;

            $patientRows[] = $row;
        }

        foreach ($profileGroups as &$group) {
            $group['avg_risk_score'] = $group['count'] > 0 ? round($group['avg_risk_score'] / $group['count'], 1) : 0.0;
            usort($group['patients'], static fn (array $a, array $b): int => $b['risk_score'] <=> $a['risk_score']);
        }
        unset($group);

        uasort($profileGroups, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        foreach ($riskGroups as &$group) {
            usort($group['patients'], static fn (array $a, array $b): int => $b['risk_score'] <=> $a['risk_score']);
        }
        unset($group);
        foreach ($behaviorGroups as &$group) {
            usort($group['patients'], static fn (array $a, array $b): int => $b['risk_score'] <=> $a['risk_score']);
        }
        unset($group);
        usort($patientRows, static fn (array $a, array $b): int => $b['risk_score'] <=> $a['risk_score']);

        return [
            'days' => $days,
            'total_patients' => count($patientRows),
            'profile_groups' => array_values($profileGroups),
            'risk_groups' => $riskGroups,
            'behavior_groups' => $behaviorGroups,
            'patient_rows' => $patientRows,
        ];
    }

    private function resolveDisease(User $patient, ?MedicalDossier $dossier): string
    {
        $disease = strtolower(trim((string) $patient->getMedicalCondition()));
        if ($dossier !== null && trim((string) $dossier->getDisease()) !== '') {
            $disease = strtolower(trim((string) $dossier->getDisease()));
        }

        return $disease !== '' ? $disease : 'non_renseignee';
    }

    private function resolveGender(User $patient): string
    {
        $gender = trim((string) $patient->getGenre());

        return $gender !== '' ? $gender : 'Non renseigne';
    }

    private function formatDisease(string $disease): string
    {
        return str_replace('_', ' ', ucfirst($disease));
    }

    private function medicalRiskScoreFromDossier(?MedicalDossier $dossier): int
    {
        if ($dossier === null) {
            return 35;
        }

        $level = strtolower(trim($dossier->getEmergencyRiskLevel()));

        return match ($level) {
            'high' => 85,
            'medium' => 60,
            'low' => 30,
            default => 40,
        };
    }

    /**
     * @param array{current_count:int,previous_count:int,drop_count:int,drop_percent:float} $dropStats
     */
    private function behaviorRiskBoost(array $dropStats, int $activityCount, int $openAlerts): int
    {
        $boost = 0;
        if ((float) ($dropStats['drop_percent'] ?? 0.0) >= 60.0 && (int) ($dropStats['previous_count'] ?? 0) >= 3) {
            $boost += 20;
        } elseif ((float) ($dropStats['drop_percent'] ?? 0.0) >= 35.0) {
            $boost += 10;
        }

        if ($activityCount === 0) {
            $boost += 12;
        }

        if ($openAlerts >= 3) {
            $boost += 15;
        } elseif ($openAlerts > 0) {
            $boost += 6;
        }

        return $boost;
    }

    private function riskLevelFromScore(int $score): string
    {
        if ($score >= 80) {
            return 'critical';
        }
        if ($score >= 65) {
            return 'high';
        }
        if ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param array{current_count:int,previous_count:int,drop_count:int,drop_percent:float} $dropStats
     */
    private function behaviorLabel(array $dropStats, int $activityCount, int $openAlerts, int $maxSecurityRisk): string
    {
        if ($openAlerts >= 3 || $maxSecurityRisk >= 80) {
            return 'comportement_suspect';
        }

        if ((float) ($dropStats['drop_percent'] ?? 0.0) >= 50.0 && (int) ($dropStats['previous_count'] ?? 0) >= 3) {
            return 'baisse_forte_activite';
        }

        if ($activityCount >= 10) {
            return 'tres_actif';
        }

        return 'stable';
    }
}
