<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 🔥 NOUVEAU CONTRÔLEUR : Gestion avancée des commandes avec Workflow & Event Sourcing
 * Démontre les patterns métier avancés implémentés
 */
#[Route('/admin/commandes', name: 'admin_commande_')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeController extends AbstractController
{
    public function __construct(
        private readonly CommandeService $commandeService,
        private readonly CommandeRepository $commandeRepository
    ) {
    }

    /**
     * Liste toutes les commandes avec leurs statuts
     */
    #[Route('', name: 'workflow_list', methods: ['GET'])]
    public function list(): Response
    {
        $commandes = $this->commandeRepository->findBy([], ['createdAt' => 'DESC']);
        $stats = $this->commandeRepository->countByStatut();

        return $this->render('admin/commande/workflow_list.html.twig', [
            'commandes' => $commandes,
            'stats' => $stats,
        ]);
    }

    /**
     * 🔥 NOUVEAU : Détails avec Workflow + Event Sourcing
     * Affiche les transitions disponibles et l'historique complet
     */
    #[Route('/{id}/workflow', name: 'workflow_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // 🔥 Récupérer les transitions disponibles (State Machine)
        $availableTransitions = $this->commandeService->getAvailableTransitions($commande);

        // 🔥 Récupérer l'historique complet (Event Sourcing)
        $history = $this->commandeService->getCommandeHistory($commande);

        return $this->render('admin/commande/workflow_show.html.twig', [
            'commande' => $commande,
            'availableTransitions' => $availableTransitions,
            'history' => $history,
        ]);
    }

    /**
     * 🔥 NOUVEAU : Applique une transition du Workflow
     * Remplace l'ancien updateStatus() par une gestion sécurisée via State Machine
     */
    #[Route('/{id}/transition/{transition}', name: 'workflow_apply_transition', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function applyTransition(
        int $id,
        string $transition,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('commande_transition_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_commande_workflow_show', ['id' => $id]);
        }

        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        // 🔥 Vérification et application de la transition via State Machine
        if (!$this->commandeService->canApplyTransition($commande, $transition)) {
            $this->addFlash('error', sprintf(
                'La transition "%s" n\'est pas possible depuis le statut "%s"',
                $transition,
                $commande->getStatut()
            ));
            return $this->redirectToRoute('admin_commande_workflow_show', ['id' => $id]);
        }

        // Appliquer la transition (déclenche automatiquement les guards et events)
        $success = $this->commandeService->applyTransition($commande, $transition);

        if ($success) {
            $this->addFlash('success', sprintf(
                'Transition "%s" appliquée avec succès. Nouveau statut: %s',
                $transition,
                $commande->getStatut()
            ));
        } else {
            $this->addFlash('error', 'La transition a été bloquée par une règle métier');
        }

        return $this->redirectToRoute('admin_commande_workflow_show', ['id' => $id]);
    }

    /**
     * 🔥 NOUVEAU : Visualisation de l'historique complet (Event Sourcing)
     */
    #[Route('/{id}/workflow/history', name: 'workflow_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function history(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $history = $this->commandeService->getCommandeHistory($commande);

        return $this->render('admin/commande/workflow_history.html.twig', [
            'commande' => $commande,
            'history' => $history,
        ]);
    }

    /**
     * Dashboard avec statistiques avancées
     */
    #[Route('/workflow/dashboard', name: 'workflow_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $stats = [
            'total_commandes' => $this->commandeRepository->countAll(),
            'revenue' => $this->commandeRepository->getTotalRevenue()['total'],
            'by_statut' => $this->commandeRepository->countByStatut(),
        ];

        return $this->render('admin/commande/workflow_dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }
}
