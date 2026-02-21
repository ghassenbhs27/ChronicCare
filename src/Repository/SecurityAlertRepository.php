<?php

namespace App\Repository;

use App\Entity\SecurityAlert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SecurityAlert>
 */
class SecurityAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityAlert::class);
    }

    public function hasRecentOpenAlert(
        string $eventType,
        \DateTimeImmutable $since,
        ?User $user = null,
        ?string $ipAddress = null
    ): bool {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.eventType = :eventType')
            ->andWhere('a.alertStatus = :statusOpen')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('eventType', $eventType)
            ->setParameter('since', $since)
            ->setParameter('statusOpen', SecurityAlert::STATUS_EN_COURS);

        if ($user !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $user);
        } elseif ($ipAddress !== null && $ipAddress !== '') {
            $qb->andWhere('a.ipAddress = :ip')->setParameter('ip', $ipAddress);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return array<int, array{
     *   user_id:int,
     *   nom:string,
     *   prenom:string,
     *   email:string,
     *   total_alerts:int,
     *   unresolved_alerts:int,
     *   max_risk:int,
     *   avg_risk:float
     * }>
     */
    public function findRiskUsersSince(\DateTimeImmutable $since, int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('u.email AS email')
            ->addSelect('COUNT(a.id) AS total_alerts')
            ->addSelect('SUM(CASE WHEN a.alertStatus = :statusOpen THEN 1 ELSE 0 END) AS unresolved_alerts')
            ->addSelect('MAX(a.riskScore) AS max_risk')
            ->addSelect('AVG(a.riskScore) AS avg_risk')
            ->innerJoin('a.user', 'u')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->setParameter('statusOpen', SecurityAlert::STATUS_EN_COURS)
            ->groupBy('u.id, u.nom, u.prenom, u.email')
            ->orderBy('unresolved_alerts', 'DESC')
            ->addOrderBy('max_risk', 'DESC')
            ->addOrderBy('total_alerts', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'nom' => (string) ($row['nom'] ?? ''),
                'prenom' => (string) ($row['prenom'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'total_alerts' => (int) ($row['total_alerts'] ?? 0),
                'unresolved_alerts' => (int) ($row['unresolved_alerts'] ?? 0),
                'max_risk' => (int) ($row['max_risk'] ?? 0),
                'avg_risk' => round((float) ($row['avg_risk'] ?? 0), 1),
            ];
        }, $rows);
    }

    /**
     * @param int[] $userIds
     * @return array<int, array<int, array{
     *   event_type:string,
     *   alert_type:string,
     *   alert_status:string,
     *   severity:string,
     *   risk_score:int,
     *   message:string,
     *   created_at:string
     * }>>
     */
    public function findRecentOpenAlertsForUsersSince(
        \DateTimeImmutable $since,
        array $userIds,
        int $perUserLimit = 6
    ): array {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('a.eventType AS event_type')
            ->addSelect('a.alertType AS alert_type')
            ->addSelect('a.alertStatus AS alert_status')
            ->addSelect('a.severity AS severity')
            ->addSelect('a.riskScore AS risk_score')
            ->addSelect('a.message AS message')
            ->addSelect('a.createdAt AS created_at')
            ->innerJoin('a.user', 'u')
            ->andWhere('a.alertStatus = :statusOpen')
            ->andWhere('a.createdAt >= :since')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('since', $since)
            ->setParameter('userIds', $userIds)
            ->setParameter('statusOpen', SecurityAlert::STATUS_EN_COURS)
            ->orderBy('u.id', 'ASC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $grouped[$userId] ??= [];
            if (count($grouped[$userId]) >= max(1, $perUserLimit)) {
                continue;
            }

            $createdAt = $row['created_at'] ?? null;
            if ($createdAt instanceof \DateTimeInterface) {
                $createdAtValue = $createdAt->format('Y-m-d H:i:s');
            } else {
                $createdAtValue = (string) $createdAt;
            }

            $grouped[$userId][] = [
                'event_type' => (string) ($row['event_type'] ?? ''),
                'alert_type' => (string) ($row['alert_type'] ?? ''),
                'alert_status' => (string) ($row['alert_status'] ?? ''),
                'severity' => (string) ($row['severity'] ?? ''),
                'risk_score' => (int) ($row['risk_score'] ?? 0),
                'message' => (string) ($row['message'] ?? ''),
                'created_at' => $createdAtValue,
            ];
        }

        return $grouped;
    }

    /**
     * @param int[] $userIds
     * @return array<int, array{open_alerts:int,max_risk:int,avg_risk:float}>
     */
    public function findOpenRiskStatsForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('u.id AS user_id')
            ->addSelect('COUNT(a.id) AS open_alerts')
            ->addSelect('MAX(a.riskScore) AS max_risk')
            ->addSelect('AVG(a.riskScore) AS avg_risk')
            ->innerJoin('a.user', 'u')
            ->andWhere('u.id IN (:userIds)')
            ->andWhere('a.alertStatus = :statusOpen')
            ->setParameter('userIds', $userIds)
            ->setParameter('statusOpen', SecurityAlert::STATUS_EN_COURS)
            ->groupBy('u.id')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $out[$userId] = [
                'open_alerts' => (int) ($row['open_alerts'] ?? 0),
                'max_risk' => (int) ($row['max_risk'] ?? 0),
                'avg_risk' => round((float) ($row['avg_risk'] ?? 0), 1),
            ];
        }

        return $out;
    }
}
