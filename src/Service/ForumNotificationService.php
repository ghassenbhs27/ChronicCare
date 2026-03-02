<?php

namespace App\Service;

use App\Entity\Commentaire;
use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ForumNotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }


    public function notifyPublicationLike(Publication $publication, User $liker): void
    {
        // Ne pas notifier si l'auteur like sa propre publication
        if (!$publication->getAuteur() || $publication->getAuteur() === $liker) {
            return;
        }

        // Vérifier si une notification similaire existe déjà
        if ($this->notificationRepository->notificationExists(
            $publication->getAuteur(),
            Notification::TYPE_LIKE_PUBLICATION,
            $publication->getId(),
            null,
            $liker
        )) {
            return;
        }

        $likerName = $this->getUserDisplayName($liker);
        $message = sprintf('%s a aimé votre publication "%s"', $likerName, $this->truncateText($publication->getTitre(), 50));
        $lien = $this->urlGenerator->generate('publication_show', ['id' => $publication->getId()]);

        $notification = new Notification();
        $notification->setDestinataire($publication->getAuteur())
            ->setEmetteur($liker)
            ->setType(Notification::TYPE_LIKE_PUBLICATION)
            ->setMessage($message)
            ->setLien($lien)
            ->setPublication($publication);

        $this->notificationRepository->save($notification, true);
    }


    public function notifyCommentaireLike(Commentaire $commentaire, User $liker): void
    {
        // Ne pas notifier si l'auteur like son propre commentaire
        if (!$commentaire->getAuteur() || $commentaire->getAuteur() === $liker) {
            return;
        }

        // Ne pas notifier les commentaires anonymes
        if ($commentaire->isAnonymous()) {
            return;
        }

        // Vérifier si une notification similaire existe déjà
        if ($this->notificationRepository->notificationExists(
            $commentaire->getAuteur(),
            Notification::TYPE_LIKE_COMMENTAIRE,
            null,
            $commentaire->getId(),
            $liker
        )) {
            return;
        }

        $likerName = $this->getUserDisplayName($liker);
        $message = sprintf('%s a aimé votre commentaire', $likerName);
        $lien = $this->urlGenerator->generate('publication_show', ['id' => $commentaire->getPublication()->getId()]) . '#comment-' . $commentaire->getId();

        $notification = new Notification();
        $notification->setDestinataire($commentaire->getAuteur())
            ->setEmetteur($liker)
            ->setType(Notification::TYPE_LIKE_COMMENTAIRE)
            ->setMessage($message)
            ->setLien($lien)
            ->setCommentaire($commentaire)
            ->setPublication($commentaire->getPublication());

        $this->notificationRepository->save($notification, true);
    }

    /**
     * Crée une notification pour un nouveau commentaire sur une publication
     */
    public function notifyNewCommentaire(Commentaire $commentaire): void
    {
        $publication = $commentaire->getPublication();

        // Ne pas notifier si c'est l'auteur de la publication qui commente
        if (!$publication->getAuteur() || $publication->getAuteur() === $commentaire->getAuteur()) {
            return;
        }

        // Ne pas notifier pour les commentaires anonymes
        if ($commentaire->isAnonymous() || !$commentaire->getAuteur()) {
            return;
        }

        // Vérifier si une notification similaire existe déjà
        if ($this->notificationRepository->notificationExists(
            $publication->getAuteur(),
            Notification::TYPE_COMMENTAIRE,
            $publication->getId(),
            null,
            $commentaire->getAuteur()
        )) {
            return;
        }

        $commenterName = $this->getUserDisplayName($commentaire->getAuteur());
        $message = sprintf('%s a commenté votre publication "%s"', $commenterName, $this->truncateText($publication->getTitre(), 50));
        $lien = $this->urlGenerator->generate('publication_show', ['id' => $publication->getId()]) . '#comment-' . $commentaire->getId();

        $notification = new Notification();
        $notification->setDestinataire($publication->getAuteur())
            ->setEmetteur($commentaire->getAuteur())
            ->setType(Notification::TYPE_COMMENTAIRE)
            ->setMessage($message)
            ->setLien($lien)
            ->setPublication($publication)
            ->setCommentaire($commentaire);

        $this->notificationRepository->save($notification, true);
    }

    /**
     * Récupère le nom d'affichage d'un utilisateur
     */
    private function getUserDisplayName(User $user): string
    {
        $nom = trim($user->getNom() . ' ' . $user->getPrenom());
        return $nom ?: 'Un utilisateur';
    }

    /**
     * Tronque un texte à une longueur donnée
     */
    private function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->entityManager->flush();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): int
    {
        return $this->notificationRepository->markAllAsReadForUser($user);
    }

    /**
     * Supprime une notification
     */
    public function delete(Notification $notification): void
    {
        $this->notificationRepository->remove($notification, true);
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     *
     * @return Notification[]
     */
    public function getUnreadNotifications(User $user): array
    {
        return $this->notificationRepository->findUnreadByUser($user);
    }

    /**
     * Compte le nombre de notifications non lues
     */
    public function countUnreadNotifications(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * Récupère toutes les notifications d'un utilisateur
     *
     * @return Notification[]
     */
    public function getAllNotifications(User $user, int $limit = 20): array
    {
        return $this->notificationRepository->findByUser($user, $limit);
    }

    /**
     * Nettoie les anciennes notifications
     */
    public function cleanOldNotifications(): int
    {
        return $this->notificationRepository->deleteOldNotifications();
    }
}