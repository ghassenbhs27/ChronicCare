<?php

namespace App\Repository;

use App\Entity\LigneCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneCommande>
 */
class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    /**
     * Obtenir toutes les lignes d'une commande (eager loading)
     * @return LigneCommande[]
     */
    /**
     * @param int|string $commande
     * @return array<int, LigneCommande>
     */
    public function findByCommande($commande): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.produit', 'p')
            ->addSelect('p')
            ->andWhere('l.commande = :commande')
            ->setParameter('commande', $commande)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total des ventes par produit
     */
    /**
     * @param int|string $produit
     */
    public function getTotalVentesByProduit($produit): ?string
    {
        return $this->createQueryBuilder('l')
            ->select('SUM(l.sousTotal)')
            ->andWhere('l.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Quantité totale vendue par produit
     */
    /**
     * @param int|string $produit
     */
    public function getTotalQuantityByProduit($produit): ?int
    {
        return $this->createQueryBuilder('l')
            ->select('SUM(l.quantite)')
            ->andWhere('l.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Produits les plus vendus
     */
    /**
     * @return array<int, array{produit: mixed, totalQty: string}>
     */
    public function getTopSellingProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->select('l.produit, SUM(l.quantite) as totalQty')
            ->groupBy('l.produit')
            ->orderBy('totalQty', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
