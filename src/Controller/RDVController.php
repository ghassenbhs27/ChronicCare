<?php

namespace App\Controller;

use App\Entity\RDV;
use App\Entity\User;
use App\Form\RDVFormType;
use App\Form\RescheduleRDVFormType;
use App\Repository\RDVRepository;
use App\Repository\UserRepository;
use App\Service\CalendarService;
use App\Service\NotificationService;
use App\Service\RDVService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient')]
final class RDVController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/rdvs', name: 'patient_rdv_list', methods: ['GET'])]
    public function listPatient(Request $request, RDVRepository $rdvRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        $demoMode = !$user instanceof User;

        $q = $request->query->get('q');
        $status = $request->query->get('status', 'all');
        $sort = $request->query->get('sort', 'date_asc');

        if ($demoMode) {
            $demoPatient = $userRepository->findFirstPatient();
            $rdvs = $demoPatient
                ? $rdvRepository->searchForPatient($demoPatient->getId(), $q, $status, $sort)
                : [];
        } else {
            $rdvs = $rdvRepository->searchForPatient($user->getId(), $q, $status, $sort);
        }

        return $this->render('rdv/patient_list.html.twig', [
            'rdvs' => $rdvs,
            'demoMode' => $demoMode,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rdv/new/{id}', name: 'patient_rdv_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(
        int $id,
        Request $request,
        UserRepository $userRepository,
        RDVService $rdvService,
        NotificationService $notificationService,
        CalendarService $calendarService
    ): Response {
        $user = $this->getUser();
        $demoMode = !$user instanceof User;
        $patient = $user instanceof User ? $user : $userRepository->findFirstPatient();

        if ($demoMode && !$patient) {
            $this->addFlash('danger', 'Aucun patient démo disponible en base.');
            return $this->redirectToRoute('professionnels_list');
        }

        $professionnel = $userRepository->find($id);
        if (!$professionnel || !in_array('ROLE_COACH', $professionnel->getRoles())) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(RDVFormType::class, null, [
            'include_professionnel' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $rdv = $rdvService->createRDV(
                    $patient,
                    $professionnel,
                    $data['dateRdv'],
                    $data['motif'] ?? null
                );
                $notificationService->sendRDVConfirmation($rdv);

                $this->addFlash('success', 'Votre rendez-vous a été créé.');
                return $this->redirectToRoute('patient_rdv_list');
            } catch (\RuntimeException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        $slots = $calendarService->getAvailableSlots($professionnel);

        return $this->render('rdv/patient_new.html.twig', [
            'form' => $form->createView(),
            'professionnel' => $professionnel,
            'slots' => $slots,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rdv/{id}', name: 'patient_rdv_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showPatient(RDV $rdv): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getPatient()->getId() != $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('rdv/patient_show.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rdv/{id}/cancel', name: 'patient_rdv_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelPatient(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getPatient()->getId() != $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('cancel_rdv_' . $rdv->getId(), (string) $request->request->get('_token'))) {
            $rdvService->cancel($rdv);
            $this->addFlash('success', 'Rendez-vous annulé.');
        }

        return $this->redirectToRoute('patient_rdv_list');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/rdv/{id}/reschedule', name: 'patient_rdv_reschedule', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reschedulePatient(Request $request, RDV $rdv, RDVService $rdvService): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $rdv->getPatient()->getId() != $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RescheduleRDVFormType::class, [
            'dateRdv' => $rdv->getDateRdv(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $rdvService->reschedule($rdv, $data['dateRdv']);
                $this->addFlash('success', 'Rendez-vous replanifié.');
                return $this->redirectToRoute('patient_rdv_list');
            } catch (\RuntimeException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->render('rdv/patient_reschedule.html.twig', [
            'form' => $form->createView(),
            'rdv' => $rdv,
        ]);
    }
}
