<?php

namespace App\Repository;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    public function searchAndSort(?string $q, string $sort='dateActivite', string $dir='DESC'): array
    {
        $allowedSort = ['id','type','duree','calories','dateActivite','createdAt'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'dateActivite';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.utilisateur','u')->addSelect('u');

        if ($q) {
            $qb->andWhere('LOWER(a.type) LIKE :q OR LOWER(a.notes) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->orderBy('a.'.$sort, $dir)->getQuery()->getResult();
    }

    public function findByUserWithSearchSort(User $user, ?string $q, string $sort = 'dateActivite', string $dir = 'DESC'): array
    {
        $allowedSort = ['id', 'type', 'duree', 'calories', 'dateActivite', 'createdAt'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'dateActivite';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.utilisateur = :user')
            ->setParameter('user', $user);

        if ($q) {
            $qb->andWhere('LOWER(a.type) LIKE :q OR LOWER(a.notes) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->orderBy('a.'.$sort, $dir)->getQuery()->getResult();
    }

    public function getGlobalStats(): array
    {
        $row = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS totalActivites')
            ->addSelect('COALESCE(AVG(a.duree), 0) AS moyenneDuree')
            ->addSelect('COALESCE(AVG(a.calories), 0) AS moyenneCalories')
            ->addSelect('COALESCE(SUM(a.calories), 0) AS totalCalories')
            ->getQuery()
            ->getSingleResult();

        return [
            'totalActivites' => (int) ($row['totalActivites'] ?? 0),
            'moyenneDuree' => (float) ($row['moyenneDuree'] ?? 0),
            'moyenneCalories' => (float) ($row['moyenneCalories'] ?? 0),
            'totalCalories' => (int) ($row['totalCalories'] ?? 0),
        ];
    }

    public function getTrendByType(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.type AS type')
            ->addSelect('COUNT(a.id) AS nb')
            ->addSelect('COALESCE(AVG(a.duree), 0) AS moyenneDuree')
            ->addSelect('COALESCE(AVG(a.calories), 0) AS moyenneCalories')
            ->groupBy('a.type')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getUserComparison(): array
    {
        return $this->createQueryBuilder('a')
            ->select('u.id AS userId')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('COUNT(a.id) AS nb')
            ->addSelect('COALESCE(SUM(a.duree), 0) AS totalDuree')
            ->addSelect('COALESCE(SUM(a.calories), 0) AS totalCalories')
            ->addSelect('COALESCE(AVG(a.calories), 0) AS moyenneCalories')
            ->leftJoin('a.utilisateur', 'u')
            ->groupBy('u.id, u.nom, u.prenom')
            ->orderBy('totalCalories', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getInactivityAnomalies(int $days = 7): array
    {
        $cutoff = (new \DateTimeImmutable('today'))->modify('-'.$days.' days');

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u.id AS userId')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('MAX(a.dateActivite) AS lastActivity')
            ->from(User::class, 'u')
            ->leftJoin('u.activites', 'a')
            ->groupBy('u.id, u.nom, u.prenom')
            ->having('MAX(a.dateActivite) IS NULL OR MAX(a.dateActivite) < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('lastActivity', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getPersonalStats(User $user): array
    {
        $row = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS totalActivites')
            ->addSelect('COALESCE(SUM(a.duree), 0) AS totalDuree')
            ->addSelect('COALESCE(SUM(a.calories), 0) AS totalCalories')
            ->addSelect('COALESCE(AVG(a.calories), 0) AS moyenneCalories')
            ->addSelect('COALESCE(AVG(a.duree), 0) AS moyenneDuree')
            ->andWhere('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'totalActivites' => (int) ($row['totalActivites'] ?? 0),
            'totalDuree' => (int) ($row['totalDuree'] ?? 0),
            'totalCalories' => (int) ($row['totalCalories'] ?? 0),
            'moyenneCalories' => (float) ($row['moyenneCalories'] ?? 0),
            'moyenneDuree' => (float) ($row['moyenneDuree'] ?? 0),
        ];
    }
}
