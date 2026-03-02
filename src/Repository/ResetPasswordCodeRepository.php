<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetPasswordCode;
use App\Entity\User;
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

    public function invalidateActiveCodesForUser(User $user): void
    {
        $now = new \DateTimeImmutable();

        $this->createQueryBuilder('c')
            ->update()
            ->set('c.usedAt', ':now')
            ->where('c.user = :user')
            ->andWhere('c.usedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function findValidCodeForUserAndHash(User $user, string $codeHash, \DateTimeImmutable $now): ?ResetPasswordCode
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.codeHash = :codeHash')
            ->andWhere('c.usedAt IS NULL')
            ->andWhere('c.expiresAt >= :now')
            ->setParameter('user', $user)
            ->setParameter('codeHash', $codeHash)
            ->setParameter('now', $now)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
