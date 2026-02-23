<?php

namespace App\Repository;

use App\Entity\UrgenceAlerte;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UrgenceAlerte>
 */
class UrgenceAlerteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrgenceAlerte::class);
    }

    /**
     * @return UrgenceAlerte[]
     */
    public function findUnreadForProfessional(User $professional, int $limit = 10, int $maxAgeMinutes = 10): array
    {
        $since = (new \DateTimeImmutable())->modify('-' . max(1, $maxAgeMinutes) . ' minutes');

        return $this->createQueryBuilder('u')
            ->leftJoin('u.patient', 'p')->addSelect('p')
            ->andWhere('u.professionnel = :professional')
            ->andWhere('u.status = :status')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('professional', $professional)
            ->setParameter('status', UrgenceAlerte::STATUS_PENDING)
            ->setParameter('since', $since)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
