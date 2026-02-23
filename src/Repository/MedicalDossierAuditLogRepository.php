<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\MedicalDossier;
use App\Entity\MedicalDossierAuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MedicalDossierAuditLog>
 */
class MedicalDossierAuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MedicalDossierAuditLog::class);
    }

    /**
     * @return array<int, MedicalDossierAuditLog>
     */
    public function findRecentForDossier(MedicalDossier $dossier, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.actor', 'a')->addSelect('a')
            ->andWhere('l.dossier = :dossier')
            ->setParameter('dossier', $dossier)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
