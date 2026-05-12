<?php

namespace App\Controller\Admin;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/evenements')]
#[IsGranted('ROLE_ADMIN')]
class EventManagementController extends AbstractController
{
    #[Route('/', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        InscriptionEvenementRepository $inscriptionRepository
    ): Response {
        $statut = $request->query->get('statut');
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $evenementRepository->findAllForAdminPaged($statut, $page, 15);
        $evenementIds = array_map(static fn (Evenement $e) => $e->getId(), $result['items']);
        $inscriptionCounts = $inscriptionRepository->countByEvenementIds($evenementIds);

        $pendingCount = (int) $evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.statut = :s')
            ->setParameter('s', Evenement::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('back/evenement/index.html.twig', [
            'evenements' => $result['items'],
            'inscription_counts' => $inscriptionCounts,
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'statut_filter' => $statut,
            'pending_count' => $pendingCount,
        ]);
    }

    #[Route('/validation', name: 'admin_evenement_validation', methods: ['GET'])]
    public function validation(EvenementRepository $evenementRepository): Response
    {
        $pending = $evenementRepository->findPendingForAdmin();

        return $this->render('back/evenement/validation.html.twig', [
            'evenements' => $pending,
        ]);
    }

    #[Route('/{id}/valider', name: 'admin_evenement_valider', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function valider(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        if (!$evenement->isEnAttente()) {
            $this->addFlash('warning', 'Cet événement n\'est pas en attente de validation.');
            return $this->redirectToRoute('admin_evenement_index');
        }
        if ($this->isCsrfTokenValid('valider_evenement_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $evenement->setStatut(Evenement::STATUT_VALIDE);
            $em->flush();
            $this->addFlash('success', 'Événement validé.');
        }
        return $this->redirect($request->headers->get('Referer', $this->generateUrl('admin_evenement_index')));
    }

    #[Route('/{id}/refuser', name: 'admin_evenement_refuser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuser(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        if (!$evenement->isEnAttente()) {
            $this->addFlash('warning', 'Cet événement n\'est pas en attente de validation.');
            return $this->redirectToRoute('admin_evenement_index');
        }
        if ($this->isCsrfTokenValid('refuser_evenement_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $evenement->setStatut(Evenement::STATUT_REFUSE);
            $em->flush();
            $this->addFlash('success', 'Événement refusé.');
        }
        return $this->redirect($request->headers->get('Referer', $this->generateUrl('admin_evenement_index')));
    }

    #[Route('/statistiques', name: 'admin_evenement_stats', methods: ['GET'])]
    public function stats(EvenementRepository $evenementRepository): Response
    {
        $participantsByEvent = $evenementRepository->getStatsParticipantsByEvent(15);
        $inscriptionsByDay = $evenementRepository->getInscriptionsByDay(30);

        $labelsEvent = array_map(fn ($r) => $r['titre'], $participantsByEvent);
        $dataEvent = array_map(fn ($r) => $r['total'], $participantsByEvent);

        $labelsDay = array_map(fn ($r) => $r['date'], $inscriptionsByDay);
        $dataDay = array_map(fn ($r) => (int) $r['total'], $inscriptionsByDay);

        return $this->render('back/evenement/stats.html.twig', [
            'labels_event' => $labelsEvent,
            'data_event' => $dataEvent,
            'labels_day' => $labelsDay,
            'data_day' => $dataDay,
            'participants_by_event' => $participantsByEvent,
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Evenement $evenement, InscriptionEvenementRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findForEvenement($evenement);

        return $this->render('back/evenement/show.html.twig', [
            'evenement' => $evenement,
            'inscriptions' => $inscriptions,
        ]);
    }

    #[Route('/{id}/export/participants.csv', name: 'admin_evenement_export_participants', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportParticipants(
        Evenement $evenement,
        InscriptionEvenementRepository $inscriptionRepository
    ): Response {
        $inscriptions = $inscriptionRepository->findForEvenement($evenement);

        $response = new StreamedResponse(function () use ($inscriptions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Prénom', 'Nom', 'Email', 'Téléphone', 'Date inscription']);
            foreach ($inscriptions as $i) {
                fputcsv($handle, [
                    $i->getPrenom(),
                    $i->getNom(),
                    $i->getEmail(),
                    $i->getTelephone() ?? '',
                    $i->getCreatedAt()->format('d/m/Y H:i'),
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="participants-evenement-%d-%s.csv"', $evenement->getId(), $evenement->getTitre() ?: 'export')
        );
        return $response;
    }

    #[Route('/export/participants.csv', name: 'admin_evenement_export_all_participants', methods: ['GET'])]
    public function exportAllParticipants(InscriptionEvenementRepository $inscriptionRepository): Response
    {
        $inscriptions = $inscriptionRepository->findRecent(5000);
        if (count($inscriptions) > 5000) {
            $inscriptions = array_slice($inscriptions, 0, 5000);
        }

        $response = new StreamedResponse(function () use ($inscriptions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Prénom', 'Nom', 'Email', 'Téléphone', 'Événement', 'Date inscription']);
            foreach ($inscriptions as $i) {
                $e = $i->getEvenement();
                fputcsv($handle, [
                    $i->getPrenom(),
                    $i->getNom(),
                    $i->getEmail(),
                    $i->getTelephone() ?? '',
                    $e ? $e->getTitre() : '',
                    $i->getCreatedAt()->format('d/m/Y H:i'),
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tous-participants-evenements.csv"');
        return $response;
    }
}
