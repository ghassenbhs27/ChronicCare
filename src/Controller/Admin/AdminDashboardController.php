<?php

namespace App\Controller\Admin;

use App\Repository\ActiviteRepository;
use App\Repository\EtatRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Repository\PublicationRepository;
use App\Repository\RDVRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private RDVRepository $rdvRepository,
        private ActiviteRepository $activiteRepository,
        private EtatRepository $etatRepository,
        private EvenementRepository $evenementRepository,
        private InscriptionEvenementRepository $inscriptionEvenementRepository,
        private PublicationRepository $publicationRepository
    ) {
    }

    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $data = $this->buildDashboardData();
        return $this->render('back/dashboard.html.twig', $data);
    }

    #[Route('/api/dashboard', name: 'admin_dashboard_api', methods: ['GET'])]
    public function apiDashboard(): JsonResponse
    {
        $data = $this->buildDashboardData();
        $json = [
            'total_users' => $data['total_users'],
            'users_percent' => $data['users_percent'],
            'total_rdv' => $data['total_rdv'],
            'total_activites' => $data['total_activites'],
            'total_etats' => $data['total_etats'],
            'total_evenements' => $data['total_evenements'],
            'total_publications' => $data['total_publications'],
            'user_growth_labels' => $data['user_growth_labels'],
            'user_growth_data' => $data['user_growth_data'],
            'activites_by_day_labels' => $data['activites_by_day_labels'],
            'activites_by_day_data' => $data['activites_by_day_data'],
            'rdv_status_distribution' => $data['rdv_status_distribution'],
            'activities' => $data['activities'],
            'recent_rdv_json' => $data['recent_rdv_json'],
            'storage_percent' => $data['storage_percent'],
            'pending_approvals' => $data['pending_approvals'],
            'pending_approvals_count' => $data['pending_approvals_count'],
        ];
        return new JsonResponse($json);
    }

    private function buildDashboardData(): array
    {
        $now = new \DateTimeImmutable();
        $prevMonth = $now->modify('-1 month')->setTime(0, 0, 0);

        // Utilisateurs
        $totalUsers = $this->userRepository->countAllUsers();
        $usersThisMonth = $this->userRepository->countUsersSince($prevMonth);
        $usersPrevPeriod = max(1, $totalUsers - $usersThisMonth);
        $usersPercent = $usersPrevPeriod > 0
            ? round((($totalUsers - $usersPrevPeriod) / $usersPrevPeriod) * 100, 1)
            : 0;

        // RDV
        $totalRdv = $this->rdvRepository->countAll();
        $recentRdvList = $this->rdvRepository->findRecent(8);
        $rdvStatusCounts = $this->rdvRepository->countByStatut();
        $rdvStatusLabels = [
            'confirme' => 'Confirmé',
            'termine' => 'Terminé',
            'en_attente' => 'En attente',
            'annule' => 'Annulé',
        ];
        $rdvStatusDistribution = [];
        foreach ($rdvStatusLabels as $key => $label) {
            $count = $rdvStatusCounts[$key] ?? 0;
            if ($count > 0 || $key === 'en_attente') {
                $rdvStatusDistribution[] = ['label' => $label, 'count' => $count];
            }
        }
        foreach ($rdvStatusCounts as $statut => $count) {
            if (!isset($rdvStatusLabels[$statut])) {
                $rdvStatusDistribution[] = ['label' => ucfirst($statut), 'count' => $count];
            }
        }
        if (empty($rdvStatusDistribution)) {
            $rdvStatusDistribution = [['label' => 'Aucun', 'count' => 1]];
        }

        // Activités & États (suivi patients)
        $activiteStats = $this->activiteRepository->getGlobalStats();
        $totalActivites = $activiteStats['totalActivites'] ?? 0;
        $etatStats = $this->etatRepository->getGlobalStats();
        $totalEtats = $etatStats['totalEtats'] ?? 0;

        // Événements & Publications
        $totalEvenements = $this->evenementRepository->countAll();
        $totalPublications = $this->publicationRepository->countAll();

        // Croissance utilisateurs sur 7 jours
        $since7 = $now->modify('-7 days');
        $byDay = $this->userRepository->registrationsByDay($since7);
        $dayMap = [];
        foreach ($byDay as $row) {
            $key = sprintf('%04d-%02d-%02d', $row['y'], $row['m'], $row['d']);
            $dayMap[$key] = (int) $row['total'];
        }
        $userGrowthLabels = [];
        $userGrowthData = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $userGrowthLabels[] = 'J ' . $d->format('d');
            $userGrowthData[] = $dayMap[$key] ?? 0;
        }

        // Activités patients par jour (7 derniers jours)
        $activitesByDay = $this->activiteRepository->getCountByDayLastDaysForPatients(7);
        $activitesDayMap = [];
        foreach ($activitesByDay as $row) {
            $date = $row['date'] ?? null;
            $key = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
            $activitesDayMap[$key] = (int) $row['total'];
        }
        $activitesByDayLabels = [];
        $activitesByDayData = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $activitesByDayLabels[] = $d->format('d/m');
            $activitesByDayData[] = $activitesDayMap[$key] ?? 0;
        }

        // Activité récente (utilisateurs, RDV, publications, inscriptions événements)
        $recentUsers = $this->userRepository->findRecentUsers(15);
        $recentRdv = $this->rdvRepository->findRecent(15);
        $recentPublications = $this->publicationRepository->findRecent(15);
        $recentInscriptions = $this->inscriptionEvenementRepository->findRecent(15);
        $pendingApprovals = $this->userRepository->findPendingProfessionalApprovals(12);
        $pendingApprovalList = [];
        foreach ($pendingApprovals as $pendingUser) {
            $created = $pendingUser->getCreatedAt();
            $requestedRole = $pendingUser->getRequestedRole() ?? 'ROLE_PATIENT';
            $roleLabel = match ($requestedRole) {
                'ROLE_COACH' => 'Coach',
                'ROLE_NUTRITIONNISTE' => 'Nutritionniste',
                default => 'Utilisateur',
            };

            $pendingApprovalList[] = [
                'id' => $pendingUser->getId(),
                'full_name' => $pendingUser->getFullName(),
                'email' => (string) $pendingUser->getEmail(),
                'role_label' => $roleLabel,
                'created_at' => $created->format('d/m/Y H:i'),
                'time' => $created->format('U'),
            ];
        }
        $activities = [];
        foreach ($recentUsers as $u) {
            $created = $u->getCreatedAt();
            $activities[] = [
                'type' => 'user',
                'label' => 'Nouvel utilisateur',
                'detail' => $u->getFullName(),
                'time' => $created->format('U'),
                'icon' => 'person-plus',
                'color' => 'purple',
            ];
        }
        foreach ($recentRdv as $r) {
            $created = $r->getCreatedAt();
            $activities[] = [
                'type' => 'rdv',
                'label' => 'Nouveau RDV',
                'detail' => $r->getPatient()->getFullName() . ' / ' . $r->getDateRdv()->format('d/m/Y'),
                'time' => $created->format('U'),
                'icon' => 'calendar-check',
                'color' => 'green',
            ];
        }
        foreach ($recentPublications as $p) {
            $created = $p->getCreatedAt();
            $activities[] = [
                'type' => 'publication',
                'label' => 'Nouvelle publication forum',
                'detail' => $p->getTitre(),
                'time' => $created->format('U'),
                'icon' => 'chat-square-text',
                'color' => 'blue',
            ];
        }
        foreach ($recentInscriptions as $i) {
            $created = $i->getCreatedAt();
            $ev = $i->getEvenement();
            $activities[] = [
                'type' => 'inscription',
                'label' => 'Inscription événement',
                'detail' => ($ev ? $ev->getTitre() : 'Événement') . ' – ' . $i->getNom(),
                'time' => $created->format('U'),
                'icon' => 'calendar-event',
                'color' => 'orange',
            ];
        }
        usort($activities, fn($a, $b) => $b['time'] <=> $a['time']);
        // Toutes les activités récentes (affichage avec scroll)
        foreach ($activities as &$a) {
            $diff = $now->getTimestamp() - $a['time'];
            if ($diff < 60) {
                $a['time_ago'] = 'À l\'instant';
            } elseif ($diff < 3600) {
                $a['time_ago'] = (int) floor($diff / 60) . ' min';
            } elseif ($diff < 86400) {
                $a['time_ago'] = (int) floor($diff / 3600) . ' h';
            } else {
                $a['time_ago'] = (int) floor($diff / 86400) . ' j';
            }
            unset($a['time']);
        }
        unset($a);

        foreach ($pendingApprovalList as &$entry) {
            $diff = $now->getTimestamp() - (int) $entry['time'];
            if ($diff < 60) {
                $entry['time_ago'] = 'A l\'instant';
            } elseif ($diff < 3600) {
                $entry['time_ago'] = (int) floor($diff / 60) . ' min';
            } elseif ($diff < 86400) {
                $entry['time_ago'] = (int) floor($diff / 3600) . ' h';
            } else {
                $entry['time_ago'] = (int) floor($diff / 86400) . ' j';
            }
            unset($entry['time']);
        }
        unset($entry);

        // Derniers RDV (pour le tableau)
        $recentRdvJson = array_map(fn($r) => [
            'id' => $r->getId(),
            'patient' => $r->getPatient()->getFullName(),
            'pro' => $r->getProfessionnel()->getFullName(),
            'date_rdv' => $r->getDateRdv()->format('d/m/Y H:i'),
            'statut' => $r->getStatut(),
        ], $recentRdvList);

        // Indicateur d'engagement (symbolique : basé sur activités + états)
        $storagePercent = min(100, (int) round(30 + ($totalActivites + $totalEtats) / max(1, $totalUsers * 2)));
        if ($storagePercent < 10) {
            $storagePercent = 42;
        }

        return [
            'total_users' => $totalUsers,
            'users_percent' => $usersPercent,
            'total_rdv' => $totalRdv,
            'total_activites' => $totalActivites,
            'total_etats' => $totalEtats,
            'total_evenements' => $totalEvenements,
            'total_publications' => $totalPublications,
            'user_growth_labels' => $userGrowthLabels,
            'user_growth_data' => $userGrowthData,
            'activites_by_day_labels' => $activitesByDayLabels,
            'activites_by_day_data' => $activitesByDayData,
            'rdv_status_distribution' => $rdvStatusDistribution,
            'activities' => $activities,
            'recent_rdv' => $recentRdvList,
            'recent_rdv_json' => $recentRdvJson,
            'storage_percent' => $storagePercent,
            'pending_approvals' => $pendingApprovalList,
            'pending_approvals_count' => count($pendingApprovalList),
        ];
    }
}
