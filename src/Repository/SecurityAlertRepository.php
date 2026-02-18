<?php

namespace App\Repository;

use App\Entity\SecurityAlert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityAlert>
 */
class SecurityAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityAlert::class);
    }

    public function hasRecentOpenAlert(
        string $eventType,
        \DateTimeImmutable $since,
        ?User $user = null,
        ?string $ipAddress = null
    ): bool {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.eventType = :eventType')
            ->andWhere('a.isResolved = false')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('eventType', $eventType)
            ->setParameter('since', $since);

        if ($user !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $user);
        } elseif ($ipAddress !== null && $ipAddress !== '') {
            $qb->andWhere('a.ipAddress = :ip')->setParameter('ip', $ipAddress);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
