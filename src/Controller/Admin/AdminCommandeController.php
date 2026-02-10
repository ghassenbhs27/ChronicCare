<?php

namespace App\Controller\Admin;

use App\Form\CommandeFormType;
use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes', name: 'admin_commande_')]
// #[IsGranted('ROLE_ADMIN')] // Désactivé temporairement pour la simulation admin
class AdminCommandeController extends AbstractController
{
    private $commandeRepository;
    private $commandeService;

    public function __construct(CommandeRepository $commandeRepository, CommandeService $commandeService)
    {
        $this->commandeRepository = $commandeRepository;
        $this->commandeService = $commandeService;
    }

    /**
     * Lister toutes les commandes
     */
    #[Route('/', name: 'list')]
    public function index(): Response
    {
        $commandes = $this->commandeRepository->findAll();

        return $this->render('admin/commande/list.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Voir les détails d'une commande
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);

        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('admin/commande/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    /**
     * Changer le statut d'une commande
     */
    #[Route('/{id}/status', name: 'change_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeStatus(int $id, Request $request): Response
    {
        $commande = $this->commandeRepository->find($id);

        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $status = $request->request->get('status');

        if ($status) {
            $this->commandeService->updateStatus($commande, $status);
            $this->addFlash('success', 'Statut mise à jour');
        }

        return $this->redirectToRoute('admin_commande_show', ['id' => $id]);
    }

    /**
     * Lister les commandes en attente
     */
    #[Route('/pending', name: 'pending')]
    public function pending(): Response
    {
        $commandes = $this->commandeService->getCommandesByStatus('en_attente');

        return $this->render('admin/commande/pending.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Lister les commandes expédiées
     */
    #[Route('/shipped', name: 'shipped')]
    public function shipped(): Response
    {
        $commandes = $this->commandeService->getCommandesByStatus('expédiée');

        return $this->render('admin/commande/shipped.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    /**
     * Statistiques des commandes
     */
    #[Route('/stats', name: 'stats')]
    public function stats(): Response
    {
        $countByStatus = $this->commandeRepository->getCountByStatut();
        $totalVentes = $this->commandeRepository->getTotalVentes();
        $recentCommandes = $this->commandeRepository->findRecent(10);

        return $this->render('admin/commande/stats.html.twig', [
            'countByStatus' => $countByStatus,
            'totalVentes' => $totalVentes,
            'recentCommandes' => $recentCommandes,
        ]);
    }

    /**
     * Filtrer et trier les commandes
     */
    #[Route('/filtre', name: 'filter')]
    public function filter(Request $request): Response
    {
        $statut = $request->query->get('statut');
        $tri = $request->query->get('tri', 'createdAt');
        $direction = $request->query->get('direction', 'DESC');

        $qb = $this->commandeRepository->createQueryBuilder('c');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Gestion du tri sur utilisateur (nom/prenom)
        if ($tri === 'utilisateur') {
            $qb->leftJoin('c.utilisateur', 'u')
               ->addSelect('u')
               ->orderBy('u.nom', $direction);
        } else {
            $qb->orderBy('c.' . $tri, $direction);
        }

        $commandes = $qb->getQuery()->getResult();

        return $this->render('admin/commande/list.html.twig', [
            'commandes' => $commandes,
            'statut' => $statut,
            'tri' => $tri,
            'direction' => $direction,
        ]);
    }
}
