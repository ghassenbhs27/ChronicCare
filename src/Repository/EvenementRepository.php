<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return array{items: Evenement[], total: int, pages: int, page: int, perPage: int}
     */
    public function searchByCoachPaged(
        User $coach,
        ?string $query,
        ?string $sort,
        ?string $order,
        int $page,
        int $perPage
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.coach = :coach')
            ->setParameter('coach', $coach);

        $query = trim((string) $query);
        if ($query !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :q OR LOWER(e.lieu) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $allowedSorts = [
            'dateDebut' => 'e.dateDebut',
            'dateFin' => 'e.dateFin',
            'titre' => 'e.titre',
            'createdAt' => 'e.createdAt',
        ];
        $sortField = $allowedSorts[$sort ?? ''] ?? 'e.dateDebut';
        $direction = strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortField, $direction);

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $pages = (int) max(1, (int) ceil($total / $perPage));

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Événements validés uniquement (liste publique).
     *
     * @return Evenement[]
     */
    public function findValidForPublic(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.statut IN (:statuts)')
            ->setParameter('statuts', [
                Evenement::STATUT_VALIDE,
                Evenement::STATUT_EN_ATTENTE,
            ])
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Événements en attente de validation (admin).
     *
     * @return Evenement[]
     */
    public function findPendingForAdmin(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.coach', 'c')
            ->addSelect('c')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', Evenement::STATUT_EN_ATTENTE)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée pour l'admin avec filtre par statut.
     *
     * @return array{items: Evenement[], total: int, pages: int, page: int, perPage: int}
     */
    public function findAllForAdminPaged(
        ?string $statut,
        int $page,
        int $perPage = 15
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.coach', 'c')
            ->addSelect('c')
            ->orderBy('e.createdAt', 'DESC');

        if ($statut !== null && $statut !== '') {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $pages = (int) max(1, ceil($total / $perPage));

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Stats participants par événement (pour graphique).
     *
     * @return array<int, array{titre: string, total: int}>
     */
    public function getStatsParticipantsByEvent(int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT e.id, e.titre, COUNT(i.id) AS total
            FROM evenement e
            LEFT JOIN inscription_evenement i ON i.evenement_id = e.id
            WHERE e.statut = :valide
            GROUP BY e.id, e.titre
            ORDER BY total DESC
            LIMIT :limit
        ';
        $limit = max(1, min($limit, 50));
        $result = $conn->executeQuery($sql, ['valide' => Evenement::STATUT_VALIDE, 'limit' => $limit]);
        $rows = $result->fetchAllAssociative();
        return array_map(function ($r) {
            return ['titre' => $r['titre'], 'total' => (int) $r['total']];
        }, $rows);
    }

    /**
     * Inscriptions par jour sur les N derniers jours (pour courbe).
     *
     * @return array<array{date: string, total: int}>
     */
    /**
     * @return array<int, array{date: string, total: int}>
     */
    public function getInscriptionsByDay(int $days = 30): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT DATE(i.created_at) AS date, COUNT(i.id) AS total
            FROM inscription_evenement i
            INNER JOIN evenement e ON e.id = i.evenement_id
            WHERE i.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(i.created_at)
            ORDER BY date ASC
        ";
        $result = $conn->executeQuery($sql, ['days' => $days]);
        return $result->fetchAllAssociative();
    }

    public function countByCoach(User $coach): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Evenement[]
     */
    public function findByCoach(User $coach, int $limit = 500): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('e.dateDebut', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.coach', 'c')
            ->addSelect('c')
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Evenement[] Returns an array of Evenement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Evenement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
