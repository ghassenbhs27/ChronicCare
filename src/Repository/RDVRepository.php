<?php

namespace App\Repository;

use App\Entity\RDV;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RDV>
 */
class RDVRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RDV::class);
    }

    /**
     * @return RDV[]
     */
    public function searchForPatient(int $patientId, ?string $q, ?string $status, string $sort): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.professionnel', 'p')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patientId);

        if ($q !== null && trim($q) !== '') {
            $term = '%' . mb_strtolower(trim($q)) . '%';
            $qb->andWhere('LOWER(p.nom) LIKE :term OR LOWER(p.prenom) LIKE :term OR LOWER(p.email) LIKE :term')
                ->setParameter('term', $term);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $status);
        }

        $direction = $sort === 'date_desc' ? 'DESC' : 'ASC';
        $qb->orderBy('r.dateRdv', $direction);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return RDV[]
     */
    public function searchForProfessionnel(int $professionnelId, ?string $q, ?string $status, string $sort): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->andWhere('r.professionnel = :professionnel')
            ->setParameter('professionnel', $professionnelId);

        if ($q !== null && trim($q) !== '') {
            $term = '%' . mb_strtolower(trim($q)) . '%';
            $qb->andWhere('LOWER(p.nom) LIKE :term OR LOWER(p.prenom) LIKE :term OR LOWER(p.email) LIKE :term')
                ->setParameter('term', $term);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('r.statut = :statut')->setParameter('statut', $status);
        }

        $direction = $sort === 'date_desc' ? 'DESC' : 'ASC';
        $qb->orderBy('r.dateRdv', $direction);

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countByStatut(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statut AS statut', 'COUNT(r.id) AS total')
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }

    /** @return array<string, int> */
    public function countByStatutForPatient(User $patient): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statut AS statut', 'COUNT(r.id) AS total')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patient)
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }

    /** @return array<string, int> */
    public function countByStatutForProfessionnel(User $professionnel): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.statut AS statut', 'COUNT(r.id) AS total')
            ->andWhere('r.professionnel = :pro')
            ->setParameter('pro', $professionnel)
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }


    /**
     * @return RDV[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->leftJoin('r.professionnel', 'pro')
            ->addSelect('p', 'pro')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isSlotTaken(int $professionnelId, \DateTimeInterface $dateRdv, ?int $excludeRdvId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.professionnel = :professionnel')
            ->andWhere('r.dateRdv = :dateRdv')
            ->andWhere('r.statut != :annule')
            ->setParameter('professionnel', $professionnelId)
            ->setParameter('dateRdv', $dateRdv)
            ->setParameter('annule', RDV::STATUT_ANNULE);

        if ($excludeRdvId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeRdvId);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function isSlotTakenForPatient(int $patientId, \DateTimeInterface $dateRdv, ?int $excludeRdvId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.patient = :patient')
            ->andWhere('r.dateRdv = :dateRdv')
            ->andWhere('r.statut != :annule')
            ->setParameter('patient', $patientId)
            ->setParameter('dateRdv', $dateRdv)
            ->setParameter('annule', RDV::STATUT_ANNULE);

        if ($excludeRdvId !== null) {
            $qb->andWhere('r.id != :excludeId')->setParameter('excludeId', $excludeRdvId);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * @return \DateTimeInterface[]
     */
    public function findReservedSlotsForProfessionnel(int $professionnelId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.dateRdv AS dateRdv')
            ->andWhere('r.professionnel = :professionnel')
            ->andWhere('r.dateRdv >= :start')
            ->andWhere('r.dateRdv <= :end')
            ->andWhere('r.statut != :annule')
            ->setParameter('professionnel', $professionnelId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('annule', RDV::STATUT_ANNULE)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row) => $row['dateRdv'], $rows);
    }

    /**
     * @return RDV[]
     */
    public function findUpcomingForReminder(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.dateRdv >= :from')
            ->andWhere('r.dateRdv <= :to')
            ->andWhere('r.statut != :annule')
            ->andWhere('r.reminderSentAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('annule', RDV::STATUT_ANNULE)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return RDV[] Returns an array of RDV objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?RDV
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
