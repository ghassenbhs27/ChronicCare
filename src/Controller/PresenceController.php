<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PresenceController extends AbstractController
{
    #[Route('/presence/ping', name: 'presence_ping', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function ping(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('presence_ping', (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['ok' => false], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 401);
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true)) {
            $user->setLastSeenAt(new \DateTimeImmutable());
            $em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/presence/offline', name: 'presence_offline', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function offline(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 401);
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true)) {
            $user->setLastSeenAt(null);
            $em->flush();
        }

        return new JsonResponse(['ok' => true]);
    }
}
