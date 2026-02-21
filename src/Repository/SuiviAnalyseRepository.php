<?php

namespace App\Repository;

use App\Entity\SuiviAnalyse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuiviAnalyse>
 */
class SuiviAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviAnalyse::class);
    }

    /**
     * @return SuiviAnalyse[]
     */
    public function findByPatient(User $patient, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.auteur', 'u')->addSelect('u')
            ->andWhere('a.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
