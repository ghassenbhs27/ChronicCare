<?php

namespace App\Service;

use App\Repository\ActiviteRepository;
use App\Repository\SecurityAlertRepository;

class InternalAiAssistantService
{
    public function __construct(
        private readonly SecurityAlertRepository $securityAlertRepository,
        private readonly ActiviteRepository $activiteRepository,
        private readonly ?RiskCopilotLlmService $riskCopilotLlmService = null
    ) {
    }

    /**
     * @return array{
     *   title:string,
     *   summary:string,
     *   intent:string,
     *   period:string,
     *   items:array<int, array<string, mixed>>,
     *   provider?:string,
     *   global_actions?:array<int, string>,
     *   top_user_email?:string,
     *   top_user_reason?:string
     * }
     */
    public function answer(string $question, bool $useExternalLlm = false): array
    {
        $normalized = mb_strtolower(trim($question));

        if ($normalized === '' || $this->containsAny($normalized, ['risque', 'risk', 'alerte'])) {
            if ($useExternalLlm) {
                return $this->buildRiskUsersCopilotAnswer($question);
            }
            return $this->buildRiskUsersAnswer();
        }

        if ($this->containsAny($normalized, ['baisse', 'drop', 'activite', 'activité'])) {
            return $this->buildActivityDropAnswer();
        }

        $risk = $this->buildRiskUsersAnswer();
        $drop = $this->buildActivityDropAnswer();

        return [
            'title' => 'Resume IA interne (hebdomadaire)',
            'intent' => 'global_summary',
            'period' => '7 derniers jours',
            'summary' => $risk['summary'] . ' ' . $drop['summary'],
            'items' => array_merge(
                array_slice($risk['items'], 0, 3),
                array_slice($drop['items'], 0, 3)
            ),
        ];
    }

    private function buildRiskUsersCopilotAnswer(string $question): array
    {
        $since = (new \DateTimeImmutable())->modify('-7 days');
        $rows = $this->securityAlertRepository->findRiskUsersSince($since, 10);

        if ($rows === []) {
            return [
                'title' => 'Risk Copilot (IA externe)',
                'intent' => 'risk_users_llm',
                'period' => '7 derniers jours',
                'summary' => 'Aucun utilisateur a risque detecte cette semaine.',
                'items' => [],
                'provider' => 'fallback',
                'global_actions' => [],
                'top_user_email' => '',
                'top_user_reason' => '',
            ];
        }

        $userIds = array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $rows);
        $openAlertsByUser = $this->securityAlertRepository->findRecentOpenAlertsForUsersSince($since, $userIds, 6);

        $analysis = $this->riskCopilotLlmService?->analyzeRiskUsers($question, $rows, $openAlertsByUser);
        if ($analysis === null) {
            $llmError = trim((string) ($this->riskCopilotLlmService?->getLastError() ?? ''));
            $fallback = $this->buildFallbackRiskCopilotInsight($rows);

            return [
                'title' => 'Risk Copilot (mode local)',
                'intent' => 'risk_users_llm',
                'period' => '7 derniers jours',
                'summary' => $llmError !== ''
                    ? 'IA externe indisponible: ' . $llmError
                    : 'IA externe indisponible. Synthese locale basee sur les alertes.',
                'items' => $fallback,
                'provider' => 'fallback',
                'global_actions' => [
                    'Verifier les comptes avec alertes ouvertes elevees.',
                    'Surveiller les connexions suspectes sur 24 heures.',
                    'Appliquer reset mot de passe pour les cas critiques.',
                ],
                'top_user_email' => (string) ($fallback[0]['email'] ?? ''),
                'top_user_reason' => 'Classement automatique base sur alertes ouvertes et score de risque.',
            ];
        }

        return [
            'title' => 'Risk Copilot (IA externe)',
            'intent' => 'risk_users_llm',
            'period' => '7 derniers jours',
            'summary' => (string) ($analysis['executive_summary'] ?? 'Synthese IA generee.'),
            'items' => (array) ($analysis['users'] ?? []),
            'provider' => (string) ($analysis['provider'] ?? 'external'),
            'global_actions' => (array) ($analysis['global_actions'] ?? []),
            'top_user_email' => (string) ($analysis['top_user_email'] ?? ''),
            'top_user_reason' => (string) ($analysis['top_user_reason'] ?? ''),
        ];
    }

    private function buildRiskUsersAnswer(): array
    {
        $since = (new \DateTimeImmutable())->modify('-7 days');
        $rows = $this->securityAlertRepository->findRiskUsersSince($since, 10);
        $highRisk = array_values(array_filter($rows, static fn (array $r): bool => (int) ($r['max_risk'] ?? 0) >= 70));

        $summary = $rows === []
            ? 'Aucun utilisateur a risque detecte cette semaine.'
            : sprintf(
                '%d utilisateur(s) presentent un risque cette semaine. %d cas sont classes haut risque (score >= 70).',
                count($rows),
                count($highRisk)
            );

        return [
            'title' => 'Utilisateurs a risque cette semaine',
            'intent' => 'risk_users',
            'period' => '7 derniers jours',
            'summary' => $summary,
            'items' => $rows,
        ];
    }

    private function buildActivityDropAnswer(): array
    {
        $rows = $this->activiteRepository->findUsersWithLargestActivityDrop(7, 10);

        $summary = $rows === []
            ? 'Aucune baisse significative d activite detectee sur la semaine.'
            : sprintf(
                '%d utilisateur(s) montrent une baisse d activite. Le plus fort recul est de %d action(s).',
                count($rows),
                (int) ($rows[0]['drop_count'] ?? 0)
            );

        return [
            'title' => 'Plus forte baisse d activite',
            'intent' => 'activity_drop',
            'period' => '7 derniers jours vs 7 jours precedents',
            'summary' => $summary,
            'items' => $rows,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildFallbackRiskCopilotInsight(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $maxRisk = (int) ($row['max_risk'] ?? 0);
            $unresolved = (int) ($row['unresolved_alerts'] ?? 0);
            $riskLevel = $maxRisk >= 85 ? 'critical' : ($maxRisk >= 70 ? 'high' : 'medium');

            $out[] = array_merge($row, [
                'risk_level' => $riskLevel,
                'why' => [
                    sprintf('%d alerte(s) ouverte(s) sur la periode recente.', $unresolved),
                    sprintf('Score de risque maximum observe: %d.', $maxRisk),
                ],
                'actions' => [
                    'Surveiller le compte en temps reel pendant 24h.',
                    'Contacter l utilisateur pour verification d identite.',
                    'Forcer un changement de mot de passe si le risque augmente.',
                ],
            ]);
        }

        return $out;
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
