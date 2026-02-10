<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\RDV;
use App\Entity\User;
use App\Form\RescheduleRDVFormType;
use App\Repository\ConsultationRepository;
use App\Repository\RDVRepository;
use App\Repository\UserRepository;
use App\Service\RDVService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pro')]
final class RDVProController extends AbstractController
{
    #[IsGranted('ROLE_COACH')]
    #[Route('/rdvs', name: 'pro_rdv_list', methods: ['GET'])]
    public function listProfessionnel(Request $request, RDVRepository $rdvRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        $demoMode = !$user instanceof User;

        $q = $request->query->get('q');
        $status = $request->query->get('status', 'all');
        $sort = $request->query->get('sort', 'date_asc');

        if ($demoMode) {
            $demoPro = $userRepository->findFirstProfessionnel();
            $rdvs = $demoPro
                ? $rdvRepository->searchForProfessionnel($demoPro->getId(), $q, $status, $sort)
                : [];
        } else {
            $rdvs = $rdvRepository->searchForProfessionnel($user->getId(), $q, $status, $sort);
        }

        return $this->render('rdv/pro_list.html.twig', [
            'rdvs' => $rdvs,
            'demoMode' => $demoMode,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/rdv/{id}/accept', name: 'pro_rdv_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getProfessionnel()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('accept_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $rdvService->accept($rdv);
            $this->addFlash('success', 'Rendez-vous accepté.');
        }

        return $this->redirectToRoute('pro_rdv_list');
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/rdv/{id}/cancel', name: 'pro_rdv_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelProfessionnel(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getProfessionnel()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('cancel_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $rdvService->cancel($rdv);
            $this->addFlash('success', 'Rendez-vous annulé.');
        }

        return $this->redirectToRoute('pro_rdv_list');
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/rdv/{id}/note', name: 'pro_rdv_note', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function note(
        Request $request,
        RDV $rdv,
        ConsultationRepository $consultationRepository,
        \Doctrine\ORM\EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getProfessionnel()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $consultation = $consultationRepository->findOneByRdv($rdv);
        if (!$consultation) {
            $consultation = new Consultation();
            $consultation->setRdv($rdv);
            $consultation->setNotes('');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('consultation_note_' . $rdv->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('pro_rdv_note', ['id' => $rdv->getId()]);
            }
            $consultation->setNotes((string) $request->request->get('notes', ''));
            $consultation->setDiagnostic($request->request->get('diagnostic') ?: null);
            if (!$consultation->getId()) {
                $em->persist($consultation);
            }
            $em->flush();
            $this->addFlash('success', 'Note de consultation enregistrée.');
            return $this->redirectToRoute('pro_rdv_list');
        }

        return $this->render('rdv/pro_note.html.twig', [
            'rdv' => $rdv,
            'consultation' => $consultation,
        ]);
    }
}
