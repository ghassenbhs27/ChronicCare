<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\ForumNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private ForumNotificationService $forumNotificationService,
        private NotificationRepository $notificationRepository
    ) {
    }

    /**
     * Liste toutes les notifications de l'utilisateur
     */
    #[Route('', name: 'notification_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $notifications = $this->forumNotificationService->getAllNotifications($user, 50);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Récupère les notifications non lues (pour l'API)
     */
    #[Route('/unread', name: 'notification_unread', methods: ['GET'])]
    public function unread(): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $this->forumNotificationService->getUnreadNotifications($user);
        $count = $this->forumNotificationService->countUnreadNotifications($user);

        $data = array_map(function (Notification $notification) {
            return [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'lien' => $notification->getLien(),
                'icon' => $notification->getIcon(),
                'colorClass' => $notification->getColorClass(),
                'timeAgo' => $notification->getTimeAgo(),
                'isRead' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $notifications);

        return $this->json([
            'success' => true,
            'count' => $count,
            'notifications' => $data,
        ]);
    }

    /**
     * Compte le nombre de notifications non lues
     */
    #[Route('/count', name: 'notification_count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->forumNotificationService->countUnreadNotifications($user);

        return $this->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Marque une notification comme lue
     */
    #[Route('/{id}/read', name: 'notification_mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            return $this->json(['success' => false, 'error' => 'Notification non trouvée'], 404);
        }

        // Vérifier que la notification appartient à l'utilisateur connecté
        if ($notification->getDestinataire() !== $this->getUser()) {
            return $this->json(['success' => false, 'error' => 'Accès refusé'], 403);
        }

        $this->forumNotificationService->markAsRead($notification);

        return $this->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Marque toutes les notifications comme lues
     */
    #[Route('/read-all', name: 'notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->getUser();
        $count = $this->forumNotificationService->markAllAsRead($user);

        return $this->json([
            'success' => true,
            'message' => sprintf('%d notification(s) marquée(s) comme lue(s)', $count),
            'count' => $count,
        ]);
    }

    /**
     * Supprime une notification
     */
    #[Route('/{id}/delete', name: 'notification_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $notification = $this->notificationRepository->find($id);

        if (!$notification) {
            throw $this->createNotFoundException('Notification non trouvée');
        }

        // Vérifier que la notification appartient à l'utilisateur connecté
        if ($notification->getDestinataire() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé');
        }

        if (!$this->isCsrfTokenValid('delete_notification_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('notification_index');
        }

        $this->forumNotificationService->delete($notification);

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'message' => 'Notification supprimée']);
        }

        $this->addFlash('success', 'Notification supprimée avec succès !');
        return $this->redirectToRoute('notification_index');
    }

    /**
     * Supprime toutes les notifications lues
     */
    #[Route('/delete-read', name: 'notification_delete_read', methods: ['POST'])]
    public function deleteAllRead(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_all_read', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'error' => 'Jeton de sécurité invalide'], 403);
        }

        $user = $this->getUser();
        $notifications = $this->notificationRepository->findBy([
            'destinataire' => $user,
            'isRead' => true,
        ]);

        $count = count($notifications);
        foreach ($notifications as $notification) {
            $this->forumNotificationService->delete($notification);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => sprintf('%d notification(s) supprimée(s)', $count),
                'count' => $count,
            ]);
        }

        $this->addFlash('success', sprintf('%d notification(s) supprimée(s)', $count));
        return $this->redirectToRoute('notification_index');
    }
}