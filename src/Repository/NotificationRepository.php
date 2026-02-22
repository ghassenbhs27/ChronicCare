<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     *
     * @param User $user
     * @return Notification[]
     */
    public function findUnreadByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinataire = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de notifications non lues
     *
     * @param User $user
     * @return int
     */
    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.destinataire = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère toutes les notifications d'un utilisateur (lues et non lues)
     *
     * @param User $user
     * @param int $limit
     * @return Notification[]
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     *
     * @param User $user
     * @return int Nombre de notifications marquées comme lues
     */
    public function markAllAsReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->where('n.destinataire = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les anciennes notifications (plus de 30 jours)
     *
     * @return int Nombre de notifications supprimées
     */
    public function deleteOldNotifications(): int
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->andWhere('n.isRead = true')
            ->setParameter('date', $thirtyDaysAgo)
            ->getQuery()
            ->execute();
    }

    /**
     * Vérifie si une notification similaire existe déjà (pour éviter les doublons)
     *
     * @param User $destinataire
     * @param string $type
     * @param int|null $publicationId
     * @param int|null $commentaireId
     * @param User|null $emetteur
     * @return bool
     */
    public function notificationExists(
        User $destinataire,
        string $type,
        ?int $publicationId = null,
        ?int $commentaireId = null,
        ?User $emetteur = null
    ): bool {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.destinataire = :destinataire')
            ->andWhere('n.type = :type')
            ->setParameter('destinataire', $destinataire)
            ->setParameter('type', $type);

        if ($publicationId) {
            $qb->andWhere('n.publication = :publication')
                ->setParameter('publication', $publicationId);
        }

        if ($commentaireId) {
            $qb->andWhere('n.commentaire = :commentaire')
                ->setParameter('commentaire', $commentaireId);
        }

        if ($emetteur) {
            $qb->andWhere('n.emetteur = :emetteur')
                ->setParameter('emetteur', $emetteur);
        }

        // Vérifier dans les dernières 24 heures pour éviter les doublons récents
        $yesterday = new \DateTimeImmutable('-24 hours');
        $qb->andWhere('n.createdAt > :yesterday')
            ->setParameter('yesterday', $yesterday);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}