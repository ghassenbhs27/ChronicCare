<?php

namespace App\Controller\Admin;

use App\Entity\Etat;
use App\Form\EtatType;
use App\Repository\EtatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/suivi/etat')]
class EtatController extends AbstractController
{
    #[Route('/vue-globale', name: 'admin_etat_vue_globale', methods: ['GET'])]
    public function vueGlobale(EtatRepository $repo): Response
    {
        $globalStats = $repo->getGlobalStats();
        $nbPatients = $repo->countPatientsAvecSuivi();
        $trendByType = $repo->getTrendByType();
        $userComparison = $repo->getUserComparison();
        $inactivityAlerts = $repo->getInactivityAnomalies(7);
        $valueAnomalies = $repo->getValueAnomalies();
        $derniersParPatient = $repo->getDerniersRelevesParPatient();

        $byDay = $repo->getCountByDayLastDays(7);
        $dayMap = [];
        foreach ($byDay as $row) {
            $dayMap[$row['date']] = (int) $row['total'];
        }
        $now = new \DateTimeImmutable();
        $chartLabels = [];
        $chartData = [];
        $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $chartLabels[] = $dayNames[(int) $d->format('N') - 1];
            $chartData[] = $dayMap[$key] ?? 0;
        }

        return $this->render('suivi/etat/vue_globale.html.twig', [
            'globalStats' => $globalStats,
            'nbPatients' => $nbPatients,
            'trendByType' => $trendByType,
            'userComparison' => $userComparison,
            'inactivityAlerts' => $inactivityAlerts,
            'valueAnomalies' => $valueAnomalies,
            'derniersParPatient' => $derniersParPatient,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
        ]);
    }

    #[Route('/', name: 'admin_etat_index', methods: ['GET'])]
    public function index(Request $request, EtatRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateReleve');
        $dir = $request->query->get('dir', 'DESC');
        $etats = $repo->searchAndSort($q, $sort, $dir);
        $globalStats = $repo->getGlobalStats();
        $trendByType = $repo->getTrendByType();
        $userComparison = $repo->getUserComparison();
        $inactivityAlerts = $repo->getInactivityAnomalies(7);
        $valueAnomalies = $repo->getValueAnomalies();

        // Données pour la courbe (7 derniers jours)
        $byDay = $repo->getCountByDayLastDays(7);
        $dayMap = [];
        foreach ($byDay as $row) {
            $dayMap[$row['date']] = (int) $row['total'];
        }
        $now = new \DateTimeImmutable();
        $chartLabels = [];
        $chartData = [];
        $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $chartLabels[] = $dayNames[(int) $d->format('N') - 1];
            $chartData[] = $dayMap[$key] ?? 0;
        }

        return $this->render(
            'suivi/etat/index.html.twig',
            compact('etats', 'q', 'sort', 'dir', 'globalStats', 'trendByType', 'userComparison', 'inactivityAlerts', 'valueAnomalies', 'chartLabels', 'chartData')
        );
    }

    #[Route('/new', name: 'admin_etat_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $etat = new Etat();
        $form = $this->createForm(EtatType::class, $etat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($etat);
            $em->flush();

            return $this->redirectToRoute('admin_etat_index');
        }

        return $this->render('suivi/etat/new.html.twig', [
            'etat' => $etat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_etat_show', methods: ['GET'])]
    public function show(Etat $etat): Response
    {
        return $this->render('suivi/etat/show.html.twig', compact('etat'));
    }

    #[Route('/{id}/edit', name: 'admin_etat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Etat $etat, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EtatType::class, $etat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('admin_etat_index');
        }

        return $this->render('suivi/etat/edit.html.twig', [
            'etat' => $etat,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_etat_delete', methods: ['POST'])]
    public function delete(Request $request, Etat $etat, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $etat->getId(), $request->request->get('_token'))) {
            $em->remove($etat);
            $em->flush();
        }

        return $this->redirectToRoute('admin_etat_index');
    }
}
