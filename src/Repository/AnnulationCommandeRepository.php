<?php

namespace App\Repository;

use App\Entity\AnnulationCommande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnnulationCommande>
 */
class AnnulationCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnnulationCommande::class);
    }
}
