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
    public function findUnreadForProfessional(User $professional, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.patient', 'p')->addSelect('p')
            ->andWhere('u.professionnel = :professional')
            ->andWhere('u.status = :status')
            ->setParameter('professional', $professional)
            ->setParameter('status', UrgenceAlerte::STATUS_PENDING)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

