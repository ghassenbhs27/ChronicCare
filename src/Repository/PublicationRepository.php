<?php

namespace App\Repository;

use App\Entity\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Publication[]
     */
    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> */
    public function countByStatut(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.statut AS statut', 'COUNT(p.id) AS total')
            ->groupBy('p.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }

    //    /**
    //     * @return Publication[] Returns an array of Publication objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Publication
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
