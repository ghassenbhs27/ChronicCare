<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /** @return Produit[] */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Produit[] */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.nom LIKE :keyword OR p.description LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Produit[] */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.categorie = :categorie')
            ->setParameter('categorie', $categorie)
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Produit[] */
    public function findLowStock(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Produit[] */
    public function findByPriceRange(float $min, float $max): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.prix >= :min')
            ->andWhere('p.prix <= :max')
            ->setParameter('min', (string) $min)
            ->setParameter('max', (string) $max)
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<array{categorie: string}> */
    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.categorie')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Produit[] Returns an array of Produit objects
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

    //    public function findOneBySomeField($value): ?Produit
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
