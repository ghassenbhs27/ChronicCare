<?php

namespace App\Repository;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * @return array<int, Activite>
     */
    public function searchAndSort(?string $q, string $sort = 'dateActivite', string $dir = 'DESC'): array
    {
        $allowedSort = ['id', 'type', 'duree', 'calories', 'dateActivite', 'createdAt'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'dateActivite';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.utilisateur', 'u')->addSelect('u')
            ->leftJoin('a.etat', 'e')->addSelect('e');

        if ($q) {
            $qb->andWhere('LOWER(a.type) LIKE :q OR LOWER(a.notes) LIKE :q OR LOWER(e.type) LIKE :q OR LOWER(e.unite) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->orderBy('a.' . $sort, $dir)->getQuery()->getResult();
    }

    /**
     * @return array<int, Activite>
     */
    public function findByUserWithSearchSort(User $user, ?string $q, string $sort = 'dateActivite', string $dir = 'DESC'): array
    {
        $allowedSort = ['id', 'type', 'duree', 'calories', 'dateActivite', 'createdAt'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'dateActivite';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.etat', 'e')->addSelect('e')
            ->andWhere('a.utilisateur = :user')
            ->setParameter('user', $user);

        if ($q) {
            $qb->andWhere('LOWER(a.type) LIKE :q OR LOWER(a.notes) LIKE :q OR LOWER(e.type) LIKE :q OR LOWER(e.unite) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->orderBy('a.' . $sort, $dir)->getQuery()->getResult();
    }

    /** @return array<array{date: string, total: int}> */
    public function getCountByDayLastDays(int $days = 7): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        return $this->createQueryBuilder('a')
            ->select('DATE(a.dateActivite) AS date', 'COUNT(a.id) AS total')
            ->andWhere('a.dateActivite >= :since')
            ->setParameter('since', $since)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Activités par jour sur les N derniers jours, pour les patients uniquement
     * (utilisateurs avec ROLE_USER, sans ROLE_COACH ni ROLE_ADMIN).
     * @return array<array{date: string, total: int}>
     */
    public function getCountByDayLastDaysForPatients(int $days = 7): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        return $this->createQueryBuilder('a')
            ->select('DATE(a.dateActivite) AS date', 'COUNT(a.id) AS total')
            ->innerJoin('a.utilisateur', 'u')
            ->andWhere('a.dateActivite >= :since')
            ->andWhere('a.utilisateur IS NOT NULL')
            ->andWhere('u.roles LIKE :roleUser')
            ->andWhere('u.roles NOT LIKE :coach')
            ->andWhere('u.roles NOT LIKE :admin')
            ->setParameter('since', $since)
            ->setParameter('roleUser', '%ROLE_USER%')
            ->setParameter('coach', '%ROLE_COACH%')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array{totalActivites: int, moyenneDuree: float, moyenneCalories: float, totalCalories: int}
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInactivityAnomalies(int $days = 7): array
    {
        $cutoff = (new \DateTimeImmutable('today'))->modify('-' . $days . ' days');

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

    /**
     * @return array<string, int|float>
     */
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

    /** @return array<array{date: string, total: int, duree: int, calories: int}> */
    public function getActivitesByDayForUser(User $user, int $days = 30): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        return $this->createQueryBuilder('a')
            ->select('DATE(a.dateActivite) AS date')
            ->addSelect('COUNT(a.id) AS total')
            ->addSelect('COALESCE(SUM(a.duree), 0) AS duree')
            ->addSelect('COALESCE(SUM(a.calories), 0) AS calories')
            ->andWhere('a.utilisateur = :user')
            ->andWhere('a.dateActivite >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return array<array{type: string, nb: int, duree: int, calories: int}> */
    public function getActivitesByTypeForUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.type AS type')
            ->addSelect('COUNT(a.id) AS nb')
            ->addSelect('COALESCE(SUM(a.duree), 0) AS duree')
            ->addSelect('COALESCE(SUM(a.calories), 0) AS calories')
            ->andWhere('a.utilisateur = :user')
            ->setParameter('user', $user)
            ->groupBy('a.type')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array<int, array{
     *   user_id:int,
     *   nom:string,
     *   prenom:string,
     *   email:string,
     *   current_count:int,
     *   previous_count:int,
     *   drop_count:int,
     *   drop_percent:float
     * }>
     */
    public function findUsersWithLargestActivityDrop(int $windowDays = 7, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();
        $currentStart = $now->modify(sprintf('-%d days', $windowDays));
        $previousStart = $now->modify(sprintf('-%d days', $windowDays * 2));

        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('u.email AS email')
            ->addSelect('SUM(CASE WHEN a.dateActivite >= :currentStart THEN 1 ELSE 0 END) AS current_count')
            ->addSelect('SUM(CASE WHEN a.dateActivite >= :previousStart AND a.dateActivite < :currentStart THEN 1 ELSE 0 END) AS previous_count')
            ->innerJoin('a.utilisateur', 'u')
            ->andWhere('a.dateActivite >= :previousStart')
            ->setParameter('currentStart', $currentStart)
            ->setParameter('previousStart', $previousStart)
            ->groupBy('u.id, u.nom, u.prenom, u.email')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $currentCount = (int) ($row['current_count'] ?? 0);
            $previousCount = (int) ($row['previous_count'] ?? 0);
            $dropCount = $previousCount - $currentCount;

            if ($previousCount <= 0 || $dropCount <= 0) {
                continue;
            }

            $out[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'nom' => (string) ($row['nom'] ?? ''),
                'prenom' => (string) ($row['prenom'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'current_count' => $currentCount,
                'previous_count' => $previousCount,
                'drop_count' => $dropCount,
                'drop_percent' => round(($dropCount / $previousCount) * 100, 1),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['drop_count'] <=> $a['drop_count']);

        return array_slice($out, 0, max(1, $limit));
    }

    /**
     * @param int[] $userIds
     * @return array<int, int>
     */
    public function findActivityCountForUsersSince(array $userIds, \DateTimeImmutable $since): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('COUNT(a.id) AS total_count')
            ->innerJoin('a.utilisateur', 'u')
            ->andWhere('u.id IN (:userIds)')
            ->andWhere('a.dateActivite >= :since')
            ->setParameter('userIds', $userIds)
            ->setParameter('since', $since)
            ->groupBy('u.id')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $out[$userId] = (int) ($row['total_count'] ?? 0);
        }

        return $out;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array{current_count:int,previous_count:int,drop_count:int,drop_percent:float}>
     */
    public function findActivityDropStatsForUsers(array $userIds, int $windowDays = 7): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $now = new \DateTimeImmutable();
        $currentStart = $now->modify(sprintf('-%d days', $windowDays));
        $previousStart = $now->modify(sprintf('-%d days', $windowDays * 2));

        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('SUM(CASE WHEN a.dateActivite >= :currentStart THEN 1 ELSE 0 END) AS current_count')
            ->addSelect('SUM(CASE WHEN a.dateActivite >= :previousStart AND a.dateActivite < :currentStart THEN 1 ELSE 0 END) AS previous_count')
            ->innerJoin('a.utilisateur', 'u')
            ->andWhere('u.id IN (:userIds)')
            ->andWhere('a.dateActivite >= :previousStart')
            ->setParameter('userIds', $userIds)
            ->setParameter('currentStart', $currentStart)
            ->setParameter('previousStart', $previousStart)
            ->groupBy('u.id')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $currentCount = (int) ($row['current_count'] ?? 0);
            $previousCount = (int) ($row['previous_count'] ?? 0);
            $dropCount = max(0, $previousCount - $currentCount);
            $dropPercent = $previousCount > 0 ? round(($dropCount / $previousCount) * 100, 1) : 0.0;

            $out[$userId] = [
                'current_count' => $currentCount,
                'previous_count' => $previousCount,
                'drop_count' => $dropCount,
                'drop_percent' => $dropPercent,
            ];
        }

        return $out;
    }

    //    /**
    //     * @return Activite[] Returns an array of Activite objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Activite
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
