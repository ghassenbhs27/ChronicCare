<?php

namespace App\Controller\Front;

use App\Entity\UrgenceAlerte;
use App\Entity\User;
use App\Repository\UrgenceAlerteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/urgence')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UrgenceFrontController extends AbstractController
{
    #[Route('/create', name: 'front_urgence_create', methods: ['POST'])]
    public function create(Request $request, UserRepository $users, EntityManagerInterface $em): RedirectResponse
    {
        $patient = $this->getAuthenticatedUser();
        if ($this->isProfessional($patient)) {
            $this->addFlash('danger', 'Seul le patient peut declencher une urgence.');
            return $this->redirectToRoute('front_etat_index');
        }

        if (!$this->isCsrfTokenValid('create_urgence', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('front_etat_index');
        }

        $proId = (int) $request->request->get('professionnel');
        $professional = $proId > 0 ? $users->find($proId) : null;
        if (!$professional instanceof User || !$this->isProfessional($professional)) {
            $this->addFlash('danger', 'Professionnel non valide.');
            return $this->redirectToRoute('front_etat_index');
        }

        $isOnline = $professional->getLastSeenAt() instanceof \DateTimeImmutable
            && $professional->getLastSeenAt() >= (new \DateTimeImmutable())->modify('-2 minutes');
        if (!$isOnline) {
            $this->addFlash('warning', 'Ce professionnel n est plus en ligne.');
            return $this->redirectToRoute('front_etat_index');
        }

        $meetingUrl = $this->buildMeetUrl($patient, $professional);
        $targetRole = in_array('ROLE_NUTRITIONNISTE', $professional->getRoles(), true) ? 'nutritionniste' : 'coach';

        $urgence = (new UrgenceAlerte())
            ->setPatient($patient)
            ->setProfessionnel($professional)
            ->setTargetRole($targetRole)
            ->setMeetingUrl($meetingUrl);

        $em->persist($urgence);
        $em->flush();

        return $this->redirectToRoute('front_urgence_room', ['id' => $urgence->getId()]);
    }

    #[Route('/{id}/join', name: 'front_urgence_join', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function join(UrgenceAlerte $urgence, EntityManagerInterface $em): RedirectResponse
    {
        $current = $this->getAuthenticatedUser();
        $isTargetPro = $urgence->getProfessionnel()?->getId() === $current->getId();
        $isPatient = $urgence->getPatient()?->getId() === $current->getId();
        if (!$isTargetPro && !$isPatient) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        if ($isTargetPro && $urgence->getStatus() !== UrgenceAlerte::STATUS_VIEWED) {
            $urgence->setStatus(UrgenceAlerte::STATUS_VIEWED);
            $urgence->setViewedAt(new \DateTimeImmutable());
            $em->flush();
        }

        return $this->redirectToRoute('front_urgence_room', ['id' => $urgence->getId()]);
    }

    #[Route('/{id}/room', name: 'front_urgence_room', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function room(UrgenceAlerte $urgence): Response
    {
        $current = $this->getAuthenticatedUser();
        $isTargetPro = $urgence->getProfessionnel()?->getId() === $current->getId();
        $isPatient = $urgence->getPatient()?->getId() === $current->getId();
        if (!$isTargetPro && !$isPatient) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }

        return $this->render('front/urgence/room.html.twig', [
            'urgence' => $urgence,
            'isPatientOwner' => $isPatient,
        ]);
    }

    #[Route('/{id}/cancel', name: 'front_urgence_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, UrgenceAlerte $urgence, EntityManagerInterface $em): RedirectResponse
    {
        $current = $this->getAuthenticatedUser();
        $isPatient = $urgence->getPatient()?->getId() === $current->getId();
        if (!$isPatient) {
            throw $this->createAccessDeniedException('Seul le patient peut annuler cet appel.');
        }

        if (!$this->isCsrfTokenValid('cancel_urgence_' . $urgence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('front_urgence_room', ['id' => $urgence->getId()]);
        }

        $urgence->setStatus(UrgenceAlerte::STATUS_CANCELLED);
        $urgence->setViewedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Appel urgent annule.');
        return $this->redirectToRoute('front_etat_index');
    }

    #[Route('/inbox', name: 'front_urgence_inbox', methods: ['GET'])]
    public function inbox(UrgenceAlerteRepository $repo): JsonResponse
    {
        $professional = $this->getAuthenticatedUser();
        if (!$this->isProfessional($professional)) {
            return new JsonResponse(['items' => []]);
        }

        $items = [];
        foreach ($repo->findUnreadForProfessional($professional, 10) as $urgence) {
            $patient = $urgence->getPatient();
            $items[] = [
                'id' => $urgence->getId(),
                'patient' => $patient ? trim($patient->getPrenom() . ' ' . $patient->getNom()) : 'Patient',
                'role' => $urgence->getTargetRole(),
                'createdAt' => $urgence->getCreatedAt()->format('d/m/Y H:i'),
                'joinUrl' => $this->generateUrl('front_urgence_join', ['id' => $urgence->getId()]),
            ];
        }

        return new JsonResponse(['items' => $items]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        return $user;
    }

    private function isProfessional(User $user): bool
    {
        $roles = $user->getRoles();
        return in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true);
    }

    private function buildMeetUrl(User $patient, User $professional): string
    {
        $base = rtrim((string) ($_ENV['JITSI_MEET_BASE_URL'] ?? 'https://meet.jit.si'), '/');
        $room = sprintf(
            'chroniccare-urgence-%d-%d-%s',
            (int) $patient->getId(),
            (int) $professional->getId(),
            substr(bin2hex(random_bytes(5)), 0, 10)
        );

        return $base . '/' . $room;
    }
}
