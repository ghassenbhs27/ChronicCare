<?php

namespace App\Controller\Front;

use App\Entity\DiscussionMessage;
use App\Entity\User;
use App\Repository\DiscussionMessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/discussion')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DiscussionController extends AbstractController
{
    #[Route('/', name: 'front_discussion_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        DiscussionMessageRepository $discussionMessageRepository,
        EntityManagerInterface $em
    ): Response {
        $current = $this->getUser();
        if (!$current instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        $isProfessional = $this->isProfessional($current);
        $partners = $this->loadPartners($current, $isProfessional, $userRepository, $discussionMessageRepository);

        $partner = $this->resolvePartner($request, $partners);
        if ($request->isMethod('POST')) {
            if (!$partner instanceof User) {
                $this->addFlash('warning', 'Destinataire invalide.');
                return $this->redirectToRoute('front_discussion_index');
            }

            $token = (string) $request->request->get('_token');
            if (
                !$this->isCsrfTokenValid('discussion_send', $token)
                && !$this->isCsrfTokenValid('discussion_send_' . $partner->getId(), $token)
            ) {
                $this->addFlash('danger', 'Token CSRF invalide.');
                return $this->redirectToRoute('front_discussion_index', ['with' => $partner->getId()]);
            }

            $content = trim((string) $request->request->get('content', ''));
            if ($content === '') {
                $this->addFlash('warning', 'Message vide.');
                return $this->redirectToRoute('front_discussion_index', ['with' => $partner->getId()]);
            }

            $message = (new DiscussionMessage())
                ->setSender($current)
                ->setRecipient($partner)
                ->setContent(mb_substr($content, 0, 2000));
            $em->persist($message);
            $em->flush();

            return $this->redirectToRoute('front_discussion_index', ['with' => $partner->getId()]);
        }

        $messages = [];
        if ($partner instanceof User) {
            $messages = $discussionMessageRepository->findConversation($current, $partner);
            $discussionMessageRepository->markAsRead($current, $partner);
        }

        return $this->render('front/discussion/index.html.twig', [
            'partners' => $partners,
            'selectedPartner' => $partner,
            'messages' => $messages,
            'isProfessional' => $isProfessional,
        ]);
    }

    #[Route('/live', name: 'front_discussion_live', methods: ['GET'])]
    public function live(
        Request $request,
        UserRepository $userRepository,
        DiscussionMessageRepository $discussionMessageRepository
    ): JsonResponse {
        $current = $this->getUser();
        if (!$current instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $isProfessional = $this->isProfessional($current);
        $partners = $this->loadPartners($current, $isProfessional, $userRepository, $discussionMessageRepository);
        $partner = $this->resolvePartner($request, $partners);

        $messages = [];
        if ($partner instanceof User) {
            $messages = $discussionMessageRepository->findConversation($current, $partner);
            $discussionMessageRepository->markAsRead($current, $partner);
        }

        $partnerPayload = array_map(function (User $u) use ($isProfessional): array {
            $roles = $u->getRoles();
            return [
                'id' => $u->getId(),
                'name' => trim($u->getPrenom() . ' ' . $u->getNom()),
                'roleLabel' => $isProfessional ? 'Patient' : (in_array('ROLE_NUTRITIONNISTE', $roles, true) ? 'Nutritionniste' : 'Coach'),
                'online' => !$isProfessional,
            ];
        }, $partners);

        $messagePayload = array_map(function (DiscussionMessage $m): array {
            $sender = $m->getSender();
            return [
                'id' => $m->getId(),
                'senderId' => $sender?->getId(),
                'senderName' => trim((string) ($sender?->getPrenom() . ' ' . $sender?->getNom())),
                'createdAt' => $m->getCreatedAt()->format('d/m/Y H:i'),
                'content' => $m->getContent(),
            ];
        }, $messages);

        return $this->json([
            'ok' => true,
            'isProfessional' => $isProfessional,
            'selectedPartnerId' => $partner?->getId(),
            'partners' => $partnerPayload,
            'messages' => $messagePayload,
        ]);
    }

    #[Route('/send', name: 'front_discussion_send', methods: ['POST'])]
    public function send(
        Request $request,
        UserRepository $userRepository,
        DiscussionMessageRepository $discussionMessageRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $current = $this->getUser();
        if (!$current instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Connexion requise.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('discussion_send', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $isProfessional = $this->isProfessional($current);
        $partners = $this->loadPartners($current, $isProfessional, $userRepository, $discussionMessageRepository);
        $partner = $this->resolvePartner($request, $partners);
        if (!$partner instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Destinataire invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ($content === '') {
            return $this->json(['ok' => false, 'message' => 'Message vide.'], Response::HTTP_BAD_REQUEST);
        }

        $message = (new DiscussionMessage())
            ->setSender($current)
            ->setRecipient($partner)
            ->setContent(mb_substr($content, 0, 2000));
        $em->persist($message);
        $em->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/unread-count', name: 'front_discussion_unread_count', methods: ['GET'])]
    public function unreadCount(DiscussionMessageRepository $discussionMessageRepository): JsonResponse
    {
        $current = $this->getUser();
        if (!$current instanceof User) {
            return $this->json(['ok' => false, 'count' => 0], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'ok' => true,
            'count' => $discussionMessageRepository->countUnreadForRecipient($current),
        ]);
    }

    /**
     * @param User[] $partners
     */
    private function resolvePartner(Request $request, array $partners): ?User
    {
        $id = (int) ($request->query->get('with') ?? $request->request->get('with'));
        if ($id > 0) {
            foreach ($partners as $partner) {
                if ($partner->getId() === $id) {
                    return $partner;
                }
            }
        }

        return $partners[0] ?? null;
    }

    private function isProfessional(User $user): bool
    {
        $roles = $user->getRoles();
        return in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true);
    }

    /**
     * @return User[]
     */
    private function loadPartners(
        User $current,
        bool $isProfessional,
        UserRepository $userRepository,
        DiscussionMessageRepository $discussionMessageRepository
    ): array {
        if ($isProfessional) {
            $partnerIds = $discussionMessageRepository->findPartnerIdsForUser($current);
            return $userRepository->findByIdsPreservingOrder($partnerIds);
        }

        return $userRepository->findOnlineProfessionnelsForDiscussion(180, $current);
    }
}
