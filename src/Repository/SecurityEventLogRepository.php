<?php

namespace App\Repository;

use App\Entity\SecurityEventLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityEventLog>
 */
class SecurityEventLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityEventLog::class);
    }

    public function countRecentByType(
        string $eventType,
        \DateTimeImmutable $since,
        ?User $user = null,
        ?string $identifier = null,
        ?string $ipAddress = null
    ): int {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.eventType = :eventType')
            ->andWhere('l.occurredAt >= :since')
            ->setParameter('eventType', $eventType)
            ->setParameter('since', $since);

        if ($user !== null) {
            $qb->andWhere('l.user = :user')->setParameter('user', $user);
        } elseif ($identifier !== null && $identifier !== '') {
            $qb->andWhere('l.identifier = :identifier')->setParameter('identifier', $identifier);
        } elseif ($ipAddress !== null && $ipAddress !== '') {
            $qb->andWhere('l.ipAddress = :ip')->setParameter('ip', $ipAddress);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
