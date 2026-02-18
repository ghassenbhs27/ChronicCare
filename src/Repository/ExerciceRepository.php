<?php

namespace App\Repository;

use App\Entity\Exercice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercice>
 */
class ExerciceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercice::class);
    }

    /**
     * @return array{items: Exercice[], total: int, pages: int, page: int, perPage: int}
     */
    public function searchByCoachPaged(
        User $coach,
        ?int $evenementId = null,
        ?string $query = null,
        ?string $sort = null,
        ?string $order = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $qb = $this->createQueryBuilder('ex')
            ->innerJoin('ex.evenement', 'ev')
            ->andWhere('ev.coach = :coach')
            ->setParameter('coach', $coach);

        if ($evenementId) {
            $qb->andWhere('ev.id = :evenementId')
                ->setParameter('evenementId', $evenementId);
        }

        $query = trim((string) $query);
        if ($query !== '') {
            $qb->andWhere('LOWER(ex.nom) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($query) . '%');
        }

        $qb->orderBy($this->resolveSort($sort), $this->resolveOrder($order))
            ->addOrderBy('ex.nom', 'ASC');

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

    private function resolveSort(?string $sort): string
    {
        $allowedSorts = [
            'nom' => 'ex.nom',
            'duree' => 'ex.duree',
            'repetitions' => 'ex.repetitions',
            'evenement' => 'ev.titre',
        ];

        return $allowedSorts[$sort ?? ''] ?? 'ex.nom';
    }

    private function resolveOrder(?string $order): string
    {
        return strtoupper((string) $order) === 'DESC' ? 'DESC' : 'ASC';
    }

    //    /**
    //     * @return Exercice[] Returns an array of Exercice objects
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

    //    public function findOneBySomeField($value): ?Exercice
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
