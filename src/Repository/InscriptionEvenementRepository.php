<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscriptionEvenement>
 */
class InscriptionEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscriptionEvenement::class);
    }

    /**
     * Vérifie si une inscription existe déjà pour cet événement et cet email.
     */
    public function findOneByEvenementAndEmail(Evenement $evenement, string $email): ?InscriptionEvenement
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.evenement = :evenement')
            ->andWhere('LOWER(i.email) = LOWER(:email)')
            ->setParameter('evenement', $evenement)
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return InscriptionEvenement[]
     */
    public function findForEvenement(Evenement $evenement): array
    {
        return $this->createQueryBuilder('inscription')
            ->andWhere('inscription.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->orderBy('inscription.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $evenementIds
     * @return array<int, int>
     */
    /**
     * @return array<int, int>
     */
    /**
     * @param array<int> $evenementIds
     * @return array<int, int>
     */
    public function countByEvenementIds(array $evenementIds): array
    {
        $evenementIds = array_values(array_filter($evenementIds, static fn ($id) => $id !== null));
        if ($evenementIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('inscription')
            ->select('IDENTITY(inscription.evenement) AS evenement_id', 'COUNT(inscription.id) AS total')
            ->andWhere('inscription.evenement IN (:ids)')
            ->setParameter('ids', $evenementIds)
            ->groupBy('inscription.evenement')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['evenement_id']] = (int) $row['total'];
        }

        return $map;
    }

    /**
     * @return InscriptionEvenement[]
     */
    public function searchForEmailWithEvenement(
        string $email,
        ?string $query,
        ?string $sort,
        ?string $order
    ): array {
        $qb = $this->createQueryBuilder('inscription')
            ->leftJoin('inscription.evenement', 'evenement')
            ->addSelect('evenement')
            ->andWhere('inscription.email = :email')
            ->setParameter('email', $email);

        $query = trim((string) $query);
        if ($query !== '') {
            $qb->andWhere('evenement.titre LIKE :q OR evenement.lieu LIKE :q')
                ->setParameter('q', '%' . $query . '%');
        }

        $allowedSorts = [
            'dateDebut' => 'evenement.dateDebut',
            'dateFin' => 'evenement.dateFin',
            'titre' => 'evenement.titre',
            'inscription' => 'inscription.createdAt',
            'createdAt' => 'inscription.createdAt',
        ];

        $sortField = $allowedSorts[$sort ?? ''] ?? 'evenement.dateDebut';
        $direction = strtoupper((string) $order) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortField, $direction);

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre total d'inscriptions pour les événements d'un coach.
     */
    public function countByCoachEvenements(User $coach): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->innerJoin('i.evenement', 'e')
            ->andWhere('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return InscriptionEvenement[]
     */
    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.evenement', 'e')
            ->addSelect('e')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
