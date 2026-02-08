<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }
 public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // New users since X days (ex: 30)
    public function countUsersSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Inscriptions par jour sur les N derniers jours
    public function registrationsByDay(\DateTimeImmutable $since): array
{
    return $this->createQueryBuilder('u')
        ->select('YEAR(u.createdAt) AS y, MONTH(u.createdAt) AS m, DAY(u.createdAt) AS d, COUNT(u.id) AS total')
        ->andWhere('u.createdAt >= :since')
        ->setParameter('since', $since)
        ->groupBy('y, m, d')
        ->orderBy('y', 'ASC')
        ->addOrderBy('m', 'ASC')
        ->addOrderBy('d', 'ASC')
        ->getQuery()
        ->getArrayResult();
}

    // Répartition par genre
    public function countByGenre(): array
    {
        // Returns: [ ['genre' => 'Homme', 'total' => 10], ... ]
        return $this->createQueryBuilder('u')
            ->select('u.genre AS genre, COUNT(u.id) AS total')
            ->groupBy('u.genre')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
    public function findAllSorted(string $sort = 'createdAt', string $order = 'DESC'): array
    {
    $allowedSorts = ['nom', 'prenom', 'createdAt'];

    if (!in_array($sort, $allowedSorts, true)) {
        $sort = 'createdAt';
    }

    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

    return $this->createQueryBuilder('u')
        ->orderBy('u.' . $sort, $order)
        ->getQuery()
        ->getResult();
    }


    public function findAllSortedAndFiltered(
        ?string $search,
        string $sort = 'createdAt',
        string $order = 'DESC'
    ): array {
        $allowedSorts = ['nom', 'prenom', 'createdAt'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'createdAt';
        }

        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('u');

        if ($search) {
            $qb->andWhere('LOWER(u.nom) LIKE :q OR LOWER(u.prenom) LIKE :q')
             ->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        return $qb
            ->orderBy('u.' . $sort, $order)
            ->getQuery()
            ->getResult();
    }

    // Répartition par rôle principal (le 1er rôle stocké dans roles[])
    public function countByMainRole(): array
    {
        // roles est un JSON/array. On fait simple: on récupère tout et on calcule en PHP (portable DB).
        $users = $this->createQueryBuilder('u')
            ->select('u.roles')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            'ROLE_PATIENT' => 0,
            'ROLE_COACH' => 0,
            'ROLE_NUTRITIONIST' => 0,
            'ROLE_ADMIN' => 0,
            'OTHER' => 0,
        ];

        foreach ($users as $row) {
            $roles = $row['roles'] ?? [];
            // on ignore ROLE_USER, on cherche un rôle "métier"
            $main = null;
            foreach ($roles as $r) {
                if ($r !== 'ROLE_USER') { $main = $r; break; }
            }
            if (!$main) $main = 'OTHER';

            if (!isset($counts[$main])) $counts['OTHER']++;
            else $counts[$main]++;
        }

        // format arrayResult-like
        $out = [];
        foreach ($counts as $role => $total) {
            $out[] = ['role' => $role, 'total' => $total];
        }

        // tri desc
        usort($out, fn($a,$b) => $b['total'] <=> $a['total']);

        return $out;
    }
}
//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

