<?php

namespace App\Repository;

use App\Entity\CommandeEvent;
use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandeEvent>
 */
class CommandeEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeEvent::class);
    }

    /**
     * Récupère l'historique complet d'une commande
     * 
     * @return CommandeEvent[]
     */
    public function findByCommande(Commande $commande): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.commande = :commande')
            ->setParameter('commande', $commande)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les événements par type
     * 
     * @return CommandeEvent[]
     */
    public function findByEventType(string $eventType): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.eventType = :type')
            ->setParameter('type', $eventType)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les derniers événements globaux
     * 
     * @return CommandeEvent[]
     */
    public function findRecentEvents(int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les événements par type pour une commande
     */
    public function countByTypeForCommande(Commande $commande, string $eventType): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.commande = :commande')
            ->andWhere('e.eventType = :type')
            ->setParameter('commande', $commande)
            ->setParameter('type', $eventType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les événements dans une plage de dates
     * 
     * @return CommandeEvent[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.createdAt >= :start')
            ->andWhere('e.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les changements de statut
     */
    public function countStatusChanges(\DateTimeInterface $since = null): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.eventType = :type')
            ->setParameter('type', 'commande.status_changed');

        if ($since !== null) {
            $qb->andWhere('e.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
