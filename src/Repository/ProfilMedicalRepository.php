<?php

namespace App\Repository;

use App\Entity\ProfilMedical;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilMedical>
 */
class ProfilMedicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilMedical::class);
    }

    public function save(ProfilMedical $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProfilMedical $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve le profil médical d'un utilisateur
     */
    public function findByUser(User $user): ?ProfilMedical
    {
        return $this->findOneBy(['utilisateur' => $user]);
    }

    /**
     * Trouve les patients avec une condition chronique spécifique
     */
    public function findByCondition(string $condition): array
    {
        return $this->createQueryBuilder('p')
            ->where('JSON_CONTAINS(p.conditionsChroniques, :condition) = 1')
            ->setParameter('condition', json_encode($condition))
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les patients avec une allergie spécifique
     */
    public function findByAllergie(string $allergie): array
    {
        return $this->createQueryBuilder('p')
            ->where('JSON_CONTAINS(p.allergies, :allergie) = 1')
            ->setParameter('allergie', json_encode($allergie))
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les patients avec un objectif santé similaire
     */
    public function findByObjectif(string $objectif): array
    {
        return $this->createQueryBuilder('p')
            ->where('JSON_CONTAINS(p.objectifsSante, :objectif) = 1')
            ->setParameter('objectif', json_encode($objectif))
            ->getQuery()
            ->getResult();
    }
}
