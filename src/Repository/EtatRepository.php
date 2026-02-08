<?php

namespace App\Repository;

use App\Entity\Etat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EtatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etat::class);
    }

    public function searchAndSort(?string $q, string $sort='dateReleve', string $dir='DESC'): array
    {
        $allowedSort = ['id','type','valeur','unite','dateReleve'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'dateReleve';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.utilisateur','u')->addSelect('u');

        if ($q) {
            $qb->andWhere('LOWER(e.type) LIKE :q OR LOWER(e.unite) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->orderBy('e.'.$sort, $dir)->getQuery()->getResult();
    }

    public function getGlobalStats(): array
    {
        $row = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS totalEtats')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenneValeur')
            ->addSelect('COUNT(DISTINCT e.type) AS nbTypes')
            ->getQuery()
            ->getSingleResult();

        return [
            'totalEtats' => (int) ($row['totalEtats'] ?? 0),
            'moyenneValeur' => (float) ($row['moyenneValeur'] ?? 0),
            'nbTypes' => (int) ($row['nbTypes'] ?? 0),
        ];
    }

    public function getTrendByType(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type AS type')
            ->addSelect('COUNT(e.id) AS nb')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenne')
            ->addSelect('MIN(e.valeur) AS minValeur')
            ->addSelect('MAX(e.valeur) AS maxValeur')
            ->groupBy('e.type')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getUserComparison(): array
    {
        return $this->createQueryBuilder('e')
            ->select('u.id AS userId')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('COUNT(e.id) AS nb')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenneValeur')
            ->leftJoin('e.utilisateur', 'u')
            ->groupBy('u.id, u.nom, u.prenom')
            ->orderBy('nb', 'DESC')
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
            ->addSelect('MAX(e.dateReleve) AS lastReleve')
            ->from(User::class, 'u')
            ->leftJoin('u.etats', 'e')
            ->groupBy('u.id, u.nom, u.prenom')
            ->having('MAX(e.dateReleve) IS NULL OR MAX(e.dateReleve) < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('lastReleve', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getValueAnomalies(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('e.id AS id, e.type AS type, e.valeur AS valeur, e.unite AS unite, e.dateReleve AS dateReleve')
            ->addSelect('u.id AS userId, u.nom AS nom, u.prenom AS prenom')
            ->leftJoin('e.utilisateur', 'u')
            ->orderBy('e.dateReleve', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $anomalies = [];
        foreach ($rows as $row) {
            $type = mb_strtolower((string) ($row['type'] ?? ''));
            $value = (float) ($row['valeur'] ?? 0);

            $isAnomaly = match (true) {
                str_contains($type, 'gly') => $value < 70 || $value > 180,
                str_contains($type, 'poids') => $value < 35 || $value > 220,
                str_contains($type, 'tension') => $value < 8 || $value > 18,
                str_contains($type, 'temp') => $value < 35 || $value > 39.5,
                default => false,
            };

            if ($isAnomaly) {
                $anomalies[] = $row;
            }
        }

        return $anomalies;
    }
}
