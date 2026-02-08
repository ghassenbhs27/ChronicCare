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

    /**
     * Trouver tous les produits actifs
     * @return Produit[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher des produits par nom ou description
     * @return Produit[]
     */
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

    /**
     * Filtrer les produits par catégorie
     * @return Produit[]
     */
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

    /**
     * Filtrer les produits par plage de prix
     * @return Produit[]
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.prix >= :minPrice AND p.prix <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir tous les catégories uniques
     * @return array
     */
    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.categorie')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Produits avec stock faible
     * @return Produit[]
     */
    public function findLowStock(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.stock <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
