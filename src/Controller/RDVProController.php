<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\RDV;
use App\Entity\User;
use App\Repository\ConsultationRepository;
use App\Repository\RDVRepository;
use App\Repository\UserRepository;
use App\Service\RDVService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/pro')]
final class RDVProController extends AbstractController
{
    #[IsGranted('ROLE_COACH')]
    #[Route('/rdvs', name: 'pro_rdv_list', methods: ['GET'])]
    public function listProfessionnel(Request $request, RDVRepository $rdvRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        $demoMode = !$user instanceof User;

        $q = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', 'all'));
        if (!in_array($status, ['all', RDV::STATUT_EN_ATTENTE, RDV::STATUT_CONFIRME, RDV::STATUT_ANNULE, RDV::STATUT_TERMINE], true)) {
            $status = 'all';
        }

        $sort = trim((string) $request->query->get('sort', 'date_asc'));
        if (!in_array($sort, ['date_asc', 'date_desc'], true)) {
            $sort = 'date_asc';
        }

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
    #[Route('/rdv/{id}/accept', name: 'pro_rdv_accept', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function accept(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getProfessionnel()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('accept_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $rdvService->accept($rdv);
            $this->addFlash('success', 'Rendez-vous accepte.');
        }

        return $this->redirectToRoute('pro_rdv_list');
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/rdv/{id}/cancel', name: 'pro_rdv_cancel', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function cancelProfessionnel(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getProfessionnel()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('cancel_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $rdvService->cancel($rdv);
            $this->addFlash('success', 'Rendez-vous annule.');
        }

        return $this->redirectToRoute('pro_rdv_list');
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/rdv/{id}/note', name: 'pro_rdv_note', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function note(
        Request $request,
        RDV $rdv,
        ConsultationRepository $consultationRepository,
        \Doctrine\ORM\EntityManagerInterface $em,
        ValidatorInterface $validator
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
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('pro_rdv_note', ['id' => $rdv->getId()]);
            }

            $consultation->setNotes(trim((string) $request->request->get('notes', '')));

            $diagnostic = trim((string) $request->request->get('diagnostic', ''));
            $consultation->setDiagnostic($diagnostic !== '' ? $diagnostic : null);

            $violations = $validator->validate($consultation);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }

                return $this->render('rdv/pro_note.html.twig', [
                    'rdv' => $rdv,
                    'consultation' => $consultation,
                ]);
            }

            if (!$consultation->getId()) {
                $em->persist($consultation);
            }

            $em->flush();
            $this->addFlash('success', 'Note de consultation enregistree.');
            return $this->redirectToRoute('pro_rdv_list');
        }

        return $this->render('rdv/pro_note.html.twig', [
            'rdv' => $rdv,
            'consultation' => $consultation,
        ]);
    }
}
