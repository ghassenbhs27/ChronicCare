<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{total: string} */
    public function getTotalRevenue(): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.total), 0) AS total')
            ->getQuery()
            ->getSingleScalarResult();
        return ['total' => (string) ($result ?? '0')];
    }

    /** @return Commande[] */
    public function findByUtilisateur(object $utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :user')
            ->setParameter('user', $utilisateur)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Commande[] */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByNumeroCommande(string $numero): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.numeroCommande = :numero')
            ->setParameter('numero', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTotalVentes(): ?string
    {
        return $this->createQueryBuilder('c')
            ->select('SUM(c.total)')
            ->andWhere('c.statut != :canceled')
            ->setParameter('canceled', 'annulée')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<array{statut: string, count: int}> */
    public function getCountByStatut(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.statut AS statut', 'COUNT(c.id) as count')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> */
    public function countByStatut(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut AS statut', 'COUNT(c.id) AS total')
            ->groupBy('c.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * @return Commande[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.utilisateur', 'u')
            ->addSelect('u')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<array{month: string, revenue: string, count: int}> */
    public function getRevenueByMonth(int $months = 12): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$months} months");
        return $this->createQueryBuilder('c')
            ->select('SUBSTRING(c.createdAt, 1, 7) AS month', 'COALESCE(SUM(c.total), 0) AS revenue', 'COUNT(c.id) AS count')
            ->andWhere('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Commande[] Returns an array of Commande objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Commande
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
