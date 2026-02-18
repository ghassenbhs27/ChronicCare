<?php

namespace App\Repository;

use App\Entity\Etat;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Etat>
 * Méthodes filtrées aux patients (ROLE_PATIENT) pour la vue globale et les stats.
 */
class EtatRepository extends ServiceEntityRepository
{
    private const ROLE_PATIENT_LIKE = '%"ROLE_PATIENT"%';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etat::class);
    }

    /** Restreint le QueryBuilder aux relevés des patients uniquement. */
    private function addPatientOnly(\Doctrine\ORM\QueryBuilder $qb, string $aliasUser = 'u'): void
    {
        $qb->andWhere($aliasUser . '.roles LIKE :rolePatient')->setParameter('rolePatient', self::ROLE_PATIENT_LIKE);
    }

    public function searchAndSort(?string $q, string $sort = 'dateReleve', string $dir = 'DESC', bool $patientsOnly = true): array
    {
        $allowedSort = ['id', 'type', 'valeur', 'unite', 'dateReleve'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'dateReleve';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.utilisateur', 'u')->addSelect('u');
        if ($patientsOnly) {
            $qb->andWhere('u.roles LIKE :rolePatient')->setParameter('rolePatient', self::ROLE_PATIENT_LIKE);
        }
        if ($q) {
            $qb->andWhere('LOWER(e.type) LIKE :q OR LOWER(e.unite) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->orderBy('e.' . $sort, $dir)->getQuery()->getResult();
    }

    public function findByUserWithSearchSort(User $user, ?string $q, string $sort = 'dateReleve', string $dir = 'DESC'): array
    {
        $allowedSort = ['id', 'type', 'valeur', 'unite', 'dateReleve'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'dateReleve';
        }
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.utilisateur', 'u')->addSelect('u')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user);

        if ($q) {
            $qb->andWhere('LOWER(e.type) LIKE :q OR LOWER(e.unite) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        return $qb->orderBy('e.' . $sort, $dir)->getQuery()->getResult();
    }

    /** @return array<array{date: string, total: int}> Relevés des patients uniquement. */
    public function getCountByDayLastDays(int $days = 7): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        $qb = $this->createQueryBuilder('e')
            ->select('DATE(e.dateReleve) AS date')
            ->addSelect('COUNT(e.id) AS total')
            ->leftJoin('e.utilisateur', 'u')
            ->andWhere('e.dateReleve >= :since')
            ->setParameter('since', $since);
        $this->addPatientOnly($qb);
        return $qb->groupBy('date')->orderBy('date', 'ASC')->getQuery()->getArrayResult();
    }

    /** Stats globales sur les relevés des patients uniquement. */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS totalEtats')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenneValeur')
            ->addSelect('COUNT(DISTINCT e.type) AS nbTypes')
            ->leftJoin('e.utilisateur', 'u');
        $this->addPatientOnly($qb);
        $row = $qb->getQuery()->getSingleResult();

        return [
            'totalEtats' => (int) ($row['totalEtats'] ?? 0),
            'moyenneValeur' => (float) ($row['moyenneValeur'] ?? 0),
            'nbTypes' => (int) ($row['nbTypes'] ?? 0),
        ];
    }

    /** Répartition par type, patients uniquement. */
    public function getTrendByType(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.type AS type')
            ->addSelect('COUNT(e.id) AS nb')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenne')
            ->addSelect('MIN(e.valeur) AS minValeur')
            ->addSelect('MAX(e.valeur) AS maxValeur')
            ->leftJoin('e.utilisateur', 'u')
            ->groupBy('e.type')
            ->orderBy('nb', 'DESC');
        $this->addPatientOnly($qb);
        return $qb->getQuery()->getArrayResult();
    }

    /** Comparaison par utilisateur : patients uniquement. */
    public function getUserComparison(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('u.id AS userId')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('COUNT(e.id) AS nb')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenneValeur')
            ->leftJoin('e.utilisateur', 'u')
            ->groupBy('u.id, u.nom, u.prenom')
            ->orderBy('nb', 'DESC');
        $this->addPatientOnly($qb);
        return $qb->getQuery()->getArrayResult();
    }

    /** Patients (ROLE_PATIENT) sans relevé récent. */
    public function getInactivityAnomalies(int $days = 7): array
    {
        $cutoff = (new \DateTimeImmutable('today'))->modify('-' . $days . ' days');

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('u.id AS userId')
            ->addSelect('u.nom AS nom')
            ->addSelect('u.prenom AS prenom')
            ->addSelect('MAX(e.dateReleve) AS lastReleve')
            ->from(User::class, 'u')
            ->leftJoin('u.etats', 'e')
            ->andWhere('u.roles LIKE :rolePatient')
            ->setParameter('rolePatient', self::ROLE_PATIENT_LIKE)
            ->groupBy('u.id, u.nom, u.prenom')
            ->having('MAX(e.dateReleve) IS NULL OR MAX(e.dateReleve) < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->orderBy('lastReleve', 'ASC');
        return $qb->getQuery()->getArrayResult();
    }

    /** Anomalies de valeurs, relevés des patients uniquement. */
    public function getValueAnomalies(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.id AS id, e.type AS type, e.valeur AS valeur, e.unite AS unite, e.dateReleve AS dateReleve')
            ->addSelect('u.id AS userId, u.nom AS nom, u.prenom AS prenom')
            ->leftJoin('e.utilisateur', 'u')
            ->orderBy('e.dateReleve', 'DESC');
        $this->addPatientOnly($qb);
        $rows = $qb->getQuery()->getArrayResult();

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

    /**
     * Nombre de patients (ROLE_PATIENT) ayant au moins un relevé.
     */
    public function countPatientsAvecSuivi(): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.utilisateur)')
            ->leftJoin('e.utilisateur', 'u')
            ->where('e.utilisateur IS NOT NULL');
        $this->addPatientOnly($qb);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Stats par type pour un utilisateur (patient).
     * @return array<array{type: string, nb: int, moyenne: float, min: float, max: float}>
     */
    public function getEtatsByTypeForUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type AS type')
            ->addSelect('COUNT(e.id) AS nb')
            ->addSelect('COALESCE(AVG(e.valeur), 0) AS moyenne')
            ->addSelect('COALESCE(MIN(e.valeur), 0) AS min')
            ->addSelect('COALESCE(MAX(e.valeur), 0) AS max')
            ->andWhere('e.utilisateur = :user')
            ->setParameter('user', $user)
            ->groupBy('e.type')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Relevés par date pour un utilisateur, optionnellement filtrés par type (pour courbe d'évolution).
     * @return array<array{date: string, valeur: float, type: string}>
     */
    public function getRelevesByDayForUser(User $user, int $days = 30, ?string $type = null): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        $qb = $this->createQueryBuilder('e')
            ->select('DATE(e.dateReleve) AS date')
            ->addSelect('e.type AS type')
            ->addSelect('AVG(e.valeur) AS valeur')
            ->andWhere('e.utilisateur = :user')
            ->andWhere('e.dateReleve >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('date')
            ->addGroupBy('e.type')
            ->orderBy('date', 'ASC');
        if ($type !== null && $type !== '') {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }
        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Derniers relevés par patient (un dernier relevé par type par utilisateur).
     * Retourne pour chaque user les derniers etats groupés par type.
     */
    public function getDerniersRelevesParPatient(int $limitTypes = 5): array
    {
        $users = $this->getUserComparison();
        $result = [];
        foreach ($users as $row) {
            $userId = $row['userId'];
            if ($userId === null) continue;
            $derniers = $this->createQueryBuilder('e')
                ->andWhere('e.utilisateur = :uid')
                ->setParameter('uid', $userId)
                ->orderBy('e.dateReleve', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();
            $parType = [];
            foreach ($derniers as $e) {
                $t = $e->getType();
                if (!isset($parType[$t])) {
                    $parType[$t] = ['valeur' => $e->getValeur(), 'unite' => $e->getUnite(), 'date' => $e->getDateReleve()];
                }
            }
            $result[] = [
                'userId' => $userId,
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'nb' => $row['nb'],
                'moyenneValeur' => $row['moyenneValeur'],
                'derniersParType' => $parType,
            ];
        }
        return $result;
    }

//    /**
//     * @return Etat[] Returns an array of Etat objects
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

//    public function findOneBySomeField($value): ?Etat
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
