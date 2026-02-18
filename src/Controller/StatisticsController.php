<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\EtatRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Repository\RDVRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class StatisticsController extends AbstractController
{
    #[Route('/statistiques', name: 'app_statistics', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $activiteRepository,
        EtatRepository $etatRepository,
        RDVRepository $rdvRepository,
        EvenementRepository $evenementRepository,
        InscriptionEvenementRepository $inscriptionRepository
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException('Connectez-vous pour accéder aux statistiques.');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if ($this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE')) {
            return $this->coachStatistics(
                $user,
                $rdvRepository,
                $evenementRepository,
                $inscriptionRepository
            );
        }

        return $this->patientStatistics(
            $user,
            $activiteRepository,
            $etatRepository,
            $rdvRepository
        );
    }

    private function patientStatistics(
        $user,
        ActiviteRepository $activiteRepository,
        EtatRepository $etatRepository,
        RDVRepository $rdvRepository
    ): Response {
        $days = min(90, max(7, (int) ($_GET['days'] ?? 30)));
        $activitesByDay = $activiteRepository->getActivitesByDayForUser($user, $days);
        $activitesByType = $activiteRepository->getActivitesByTypeForUser($user);
        $activiteStats = $activiteRepository->getPersonalStats($user);
        $etatsByType = $etatRepository->getEtatsByTypeForUser($user);
        $relevesByDay = $etatRepository->getRelevesByDayForUser($user, $days);
        $rdvByStatut = $rdvRepository->countByStatutForPatient($user);
        $rdvs = $rdvRepository->searchForPatient($user->getId(), null, 'all', 'date_desc');
        $rdvByMonth = $this->groupRdvsByMonth($rdvs, 12);

        $chartActivites = $this->buildChartActivitesByDay($activitesByDay);
        $chartActivitesByType = $this->buildChartActivitesByType($activitesByType);
        $chartEtatsByType = $this->buildChartEtatsByType($etatsByType);
        $chartRelevesEvolution = $this->buildChartRelevesEvolution($relevesByDay);
        $chartRdvStatut = $this->buildChartRdvStatut($rdvByStatut);
        $chartRdvByMonth = $this->buildChartRdvByMonth($rdvByMonth);

        $totalRdv = array_sum($rdvByStatut);

        return $this->render('statistics/patient.html.twig', [
            'activiteStats' => $activiteStats,
            'etatsByType' => $etatsByType,
            'rdvByStatut' => $rdvByStatut,
            'totalRdv' => $totalRdv,
            'chart_activites_by_day' => $chartActivites,
            'chart_activites_by_type' => $chartActivitesByType,
            'chart_etats_by_type' => $chartEtatsByType,
            'chart_releves_evolution' => $chartRelevesEvolution,
            'chart_rdv_statut' => $chartRdvStatut,
            'chart_rdv_by_month' => $chartRdvByMonth,
            'days' => $days,
        ]);
    }

    private function coachStatistics(
        $user,
        RDVRepository $rdvRepository,
        EvenementRepository $evenementRepository,
        InscriptionEvenementRepository $inscriptionRepository
    ): Response {
        $rdvByStatut = $rdvRepository->countByStatutForProfessionnel($user);
        $rdvs = $rdvRepository->searchForProfessionnel($user->getId(), null, 'all', 'date_desc');
        $rdvByMonth = $this->groupRdvsByMonth($rdvs, 12);
        $eventsCount = $evenementRepository->countByCoach($user);
        $events = $evenementRepository->findByCoach($user);
        $inscriptionsCount = $inscriptionRepository->countByCoachEvenements($user);
        $eventsByMonth = $this->groupEventsByMonth($events, 12);

        $chartRdvStatut = $this->buildChartRdvStatut($rdvByStatut);
        $chartRdvByMonth = $this->buildChartRdvByMonth($rdvByMonth);
        $chartEventsByMonth = $this->buildChartEventsByMonth($eventsByMonth);

        $totalRdv = array_sum($rdvByStatut);

        return $this->render('statistics/coach.html.twig', [
            'rdvByStatut' => $rdvByStatut,
            'totalRdv' => $totalRdv,
            'eventsCount' => $eventsCount,
            'inscriptionsCount' => $inscriptionsCount,
            'chart_rdv_statut' => $chartRdvStatut,
            'chart_rdv_by_month' => $chartRdvByMonth,
            'chart_events_by_month' => $chartEventsByMonth,
        ]);
    }

    private function groupRdvsByMonth(array $rdvs, int $months): array
    {
        $since = (new \DateTime())->modify("-{$months} months");
        $byMonth = [];
        for ($i = 0; $i < $months; $i++) {
            $m = (clone $since)->modify("+{$i} month");
            $byMonth[$m->format('Y-m')] = 0;
        }
        foreach ($rdvs as $rdv) {
            $date = $rdv->getDateRdv();
            if ($date instanceof \DateTimeInterface) {
                $key = $date->format('Y-m');
                if (!isset($byMonth[$key])) {
                    $byMonth[$key] = 0;
                }
                $byMonth[$key]++;
            }
        }
        ksort($byMonth);
        return array_map(fn ($k, $v) => ['month' => $k, 'total' => $v], array_keys($byMonth), array_values($byMonth));
    }

    private function groupEventsByMonth(array $events, int $months): array
    {
        $since = (new \DateTime())->modify("-{$months} months");
        $byMonth = [];
        for ($i = 0; $i < $months; $i++) {
            $m = (clone $since)->modify("+{$i} month");
            $byMonth[$m->format('Y-m')] = 0;
        }
        foreach ($events as $event) {
            $date = $event->getDateDebut();
            if ($date instanceof \DateTimeInterface) {
                $key = $date->format('Y-m');
                if (!isset($byMonth[$key])) {
                    $byMonth[$key] = 0;
                }
                $byMonth[$key]++;
            }
        }
        ksort($byMonth);
        return array_map(fn ($k, $v) => ['month' => $k, 'total' => $v], array_keys($byMonth), array_values($byMonth));
    }

    private function buildChartActivitesByDay(array $data): array
    {
        $labels = array_map(fn ($r) => $r['date'], $data);
        $totals = array_map(fn ($r) => (int) $r['total'], $data);
        $durees = array_map(fn ($r) => (int) $r['duree'], $data);
        $calories = array_map(fn ($r) => (int) $r['calories'], $data);
        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Nombre d\'activités', 'data' => $totals, 'borderColor' => '#049ebb', 'backgroundColor' => 'rgba(4, 158, 187, 0.2)', 'fill' => true],
                ['label' => 'Durée (min)', 'data' => $durees, 'borderColor' => '#18444c', 'backgroundColor' => 'rgba(24, 68, 76, 0.2)', 'fill' => true],
                ['label' => 'Calories', 'data' => $calories, 'borderColor' => '#0d6efd', 'backgroundColor' => 'rgba(13, 110, 253, 0.2)', 'fill' => true],
            ],
        ];
    }

    private function buildChartActivitesByType(array $data): array
    {
        $labels = array_map(fn ($r) => $r['type'], $data);
        $values = array_map(fn ($r) => (int) $r['nb'], $data);
        return [
            'labels' => $labels,
            'datasets' => [['data' => $values, 'backgroundColor' => ['#049ebb', '#18444c', '#0d6efd', '#198754', '#fd7e14', '#6f42c1']]],
        ];
    }

    private function buildChartEtatsByType(array $data): array
    {
        $labels = array_map(fn ($r) => $r['type'], $data);
        $values = array_map(fn ($r) => (int) $r['nb'], $data);
        return [
            'labels' => $labels,
            'datasets' => [['data' => $values, 'backgroundColor' => ['#198754', '#0dcaf0', '#ffc107', '#dc3545', '#6f42c1']]],
        ];
    }

    private function buildChartRelevesEvolution(array $data): array
    {
        $byType = [];
        $allDates = [];
        foreach ($data as $r) {
            $type = $r['type'];
            $date = $r['date'];
            $allDates[$date] = true;
            if (!isset($byType[$type])) {
                $byType[$type] = [];
            }
            $byType[$type][$date] = round((float) $r['valeur'], 2);
        }
        $labels = array_keys($allDates);
        sort($labels);
        $colors = ['#049ebb', '#18444c', '#dc3545', '#198754', '#fd7e14'];
        $datasets = [];
        $i = 0;
        foreach ($byType as $type => $valuesByDate) {
            $color = $colors[$i % count($colors)];
            $values = [];
            foreach ($labels as $d) {
                $values[] = $valuesByDate[$d] ?? null;
            }
            $datasets[] = [
                'label' => $type,
                'data' => $values,
                'borderColor' => $color,
                'backgroundColor' => $color . '33',
                'fill' => false,
            ];
            $i++;
        }
        return ['labels' => $labels, 'datasets' => $datasets];
    }

    private function buildChartRdvStatut(array $rdvByStatut): array
    {
        $order = ['en_attente', 'confirme', 'termine', 'annule'];
        $labels = [];
        $data = [];
        foreach ($order as $s) {
            if (isset($rdvByStatut[$s])) {
                $labels[] = ucfirst($s);
                $data[] = $rdvByStatut[$s];
            }
        }
        if ($labels === []) {
            $labels = ['Aucun'];
            $data = [1];
        }
        return [
            'labels' => $labels,
            'datasets' => [['data' => $data, 'backgroundColor' => ['#ffc107', '#198754', '#0d6efd', '#6c757d']]],
        ];
    }

    private function buildChartRdvByMonth(array $rdvByMonth): array
    {
        $labels = array_map(fn ($r) => $r['month'], $rdvByMonth);
        $values = array_map(fn ($r) => $r['total'], $rdvByMonth);
        return [
            'labels' => $labels,
            'datasets' => [['label' => 'RDV', 'data' => $values, 'borderColor' => '#049ebb', 'backgroundColor' => 'rgba(4, 158, 187, 0.2)', 'fill' => true]],
        ];
    }

    private function buildChartEventsByMonth(array $eventsByMonth): array
    {
        $labels = array_map(fn ($r) => $r['month'], $eventsByMonth);
        $values = array_map(fn ($r) => $r['total'], $eventsByMonth);
        return [
            'labels' => $labels,
            'datasets' => [['label' => 'Événements', 'data' => $values, 'borderColor' => '#18444c', 'backgroundColor' => 'rgba(24, 68, 76, 0.2)', 'fill' => true]],
        ];
    }
}
