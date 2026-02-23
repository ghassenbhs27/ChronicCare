<?php

namespace App\Repository;

use App\Entity\DiscussionMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiscussionMessage>
 */
class DiscussionMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionMessage::class);
    }

    /**
     * @return DiscussionMessage[]
     */
    public function findConversation(User $a, User $b, int $limit = 120): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.sender = :a AND m.recipient = :b) OR (m.sender = :b AND m.recipient = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAsRead(User $recipient, User $sender): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.readAt', ':now')
            ->where('m.recipient = :recipient')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('recipient', $recipient)
            ->setParameter('sender', $sender)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function countUnreadForRecipient(User $recipient): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.recipient = :recipient')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns partner user IDs for a user, ordered by latest discussion activity first.
     *
     * @return int[]
     */
    public function findPartnerIdsForUser(User $current, int $limit = 300): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.sender) AS senderId', 'IDENTITY(m.recipient) AS recipientId')
            ->andWhere('m.sender = :current OR m.recipient = :current')
            ->setParameter('current', $current)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(50, $limit))
            ->getQuery()
            ->getArrayResult();

        $currentId = $current->getId();
        $partnerIds = [];
        foreach ($rows as $row) {
            $senderId = (int) ($row['senderId'] ?? 0);
            $recipientId = (int) ($row['recipientId'] ?? 0);
            $partnerId = $senderId === $currentId ? $recipientId : $senderId;

            if ($partnerId > 0 && !isset($partnerIds[$partnerId])) {
                $partnerIds[$partnerId] = true;
            }
        }

        return array_map('intval', array_keys($partnerIds));
    }
}
