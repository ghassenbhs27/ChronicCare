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

    /**
     * Obtenir toutes les commandes d'un utilisateur
     * @return Commande[]
     */
    public function findByUtilisateur($utilisateur): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.utilisateur = :user')
            ->setParameter('user', $utilisateur)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtrer les commandes par statut
     * @return Commande[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes récentes
     * @return Commande[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver une commande par numéro
     */
    public function findByNumeroCommande(string $numero): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.numeroCommande = :numero')
            ->setParameter('numero', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Commandes entre deux dates
     * @return Commande[]
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.createdAt >= :start')
            ->andWhere('c.createdAt <= :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Total des ventes
     */
    public function getTotalVentes(): ?string
    {
        return $this->createQueryBuilder('c')
            ->select('SUM(c.total)')
            ->andWhere('c.statut != :canceled')
            ->setParameter('canceled', 'annulée')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de commandes par statut
     */
    public function getCountByStatut(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as count')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();
    }
}
