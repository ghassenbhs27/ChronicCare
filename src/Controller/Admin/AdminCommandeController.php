<?php

namespace App\Controller\Admin;

use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/commandes', name: 'admin_commande_')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeController extends AbstractController
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly CommandeService $commandeService
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $statut = $request->query->get('statut');
        $tri = $request->query->get('tri', 'createdAt');
        $direction = $request->query->get('direction', 'DESC');

        $qb = $this->commandeRepository->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')->addSelect('u');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        if ($tri === 'utilisateur') {
            $qb->orderBy('u.nom', $direction);
        } else {
            $qb->orderBy('c.' . $tri, $direction);
        }

        $commandes = $qb->getQuery()->getResult();

        return $this->render('admin/commande/list.html.twig', [
            'commandes' => $commandes,
            'statut' => $statut ?? '',
            'tri' => $tri,
            'direction' => $direction,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('admin/commande/show.html.twig', ['commande' => $commande]);
    }

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
            $this->addFlash('success', 'Statut mis à jour');
        }

        return $this->redirectToRoute('admin_commande_show', ['id' => $id]);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): Response
    {
        return $this->render('admin/commande/pending.html.twig', [
            'commandes' => $this->commandeService->getCommandesByStatus('en_attente'),
        ]);
    }

    #[Route('/shipped', name: 'shipped', methods: ['GET'])]
    public function shipped(): Response
    {
        return $this->render('admin/commande/shipped.html.twig', [
            'commandes' => $this->commandeService->getCommandesByStatus('expédiée'),
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('admin/commande/stats.html.twig', [
            'countByStatut' => $this->commandeRepository->getCountByStatut(),
            'totalVentes' => $this->commandeRepository->getTotalVentes(),
            'recentCommandes' => $this->commandeRepository->findRecent(10),
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(): Response
    {
        $countByStatut = $this->commandeRepository->getCountByStatut();
        $totalVentes = $this->commandeRepository->getTotalVentes();
        $recentCommandes = $this->commandeRepository->findRecent(50);

        $html = $this->renderView('admin/commande/pdf.html.twig', [
            'countByStatut' => $countByStatut,
            'totalVentes' => $totalVentes,
            'recentCommandes' => $recentCommandes,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'rapport_commandes_' . (new \DateTimeImmutable())->format('Y-m-d_H-i') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
