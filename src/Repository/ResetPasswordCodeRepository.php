<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetPasswordCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResetPasswordCode>
 */
class ResetPasswordCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordCode::class);
    }

    public function deleteForEmail(string $email): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->where('c.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }

    public function findLatestActiveByEmail(string $email): ?ResetPasswordCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :email')
            ->andWhere('c.usedAt IS NULL')
            ->andWhere('c.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function purgeExpired(): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.expiresAt <= :now OR c.usedAt IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable('-1 day'))
            ->getQuery()
            ->execute();
    }
}
