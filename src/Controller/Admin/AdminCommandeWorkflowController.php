<?php

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use App\Service\CommandeEventStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes', name: 'admin_commande_')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeWorkflowController extends AbstractController
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly CommandeService $commandeService,
        private readonly CommandeEventStore $eventStore
    ) {
    }

    /**
     * Tableau de bord des commandes avec workflow
     */
    #[Route('/workflow', name: 'workflow_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $statistics = [
            'en_attente' => $this->commandeRepository->count(['statut' => 'en_attente']),
            'validee' => $this->commandeRepository->count(['statut' => 'validee']),
            'expediee' => $this->commandeRepository->count(['statut' => 'expediee']),
            'livree' => $this->commandeRepository->count(['statut' => 'livree']),
            'annulee' => $this->commandeRepository->count(['statut' => 'annulee']),
        ];

        $recentCommandes = $this->commandeRepository->findBy([], ['createdAt' => 'DESC'], 20);
        
        $eventStats = $this->eventStore->getEventStatistics();

        return $this->render('admin/commande/workflow_dashboard.html.twig', [
            'statistics' => $statistics,
            'commandes' => $recentCommandes,
            'event_stats' => $eventStats,
        ]);
    }

    /**
     * Détails d'une commande avec historique complet
     */
    #[Route('/{id}/workflow', name: 'workflow_details', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function workflowDetails(Commande $commande): Response
    {
        $history = $this->commandeService->getCommandeHistory($commande);
        $availableTransitions = $this->commandeService->getAvailableTransitions($commande);

        return $this->render('admin/commande/workflow_details.html.twig', [
            'commande' => $commande,
            'history' => $history,
            'transitions' => $availableTransitions,
        ]);
    }

    /**
     * Appliquer une transition
     */
    #[Route('/{id}/transition/{transition}', name: 'apply_transition', methods: ['POST'])]
    public function applyTransition(Commande $commande, string $transition, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('transition_' . $commande->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_commande_workflow_details', ['id' => $commande->getId()]);
        }

        if ($this->commandeService->applyTransition($commande, $transition)) {
            $this->addFlash('success', sprintf('Transition "%s" appliquée avec succès', $transition));
        } else {
            $this->addFlash('error', sprintf('Impossible d\'appliquer la transition "%s"', $transition));
        }

        return $this->redirectToRoute('admin_commande_workflow_details', ['id' => $commande->getId()]);
    }

    /**
     * Vue du graph du workflow (visualisation)
     */
    #[Route('/workflow/graph', name: 'workflow_graph', methods: ['GET'])]
    public function workflowGraph(): Response
    {
        // Cette page affichera un diagramme Mermaid.js du workflow
        return $this->render('admin/commande/workflow_graph.html.twig');
    }
}
