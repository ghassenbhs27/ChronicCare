<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\MedicalDossier;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MedicalDossier>
 */
class MedicalDossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MedicalDossier::class);
    }

    public function findOneByUser(User $user): ?MedicalDossier
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * @param array<int, User> $users
     * @return array<int, MedicalDossier>
     */
    public function findByUsers(array $users): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (User $user): int => (int) ($user->getId() ?? 0),
            $users
        ), static fn (int $id): bool => $id > 0)));

        if ($userIds === []) {
            return [];
        }

        return $this->createQueryBuilder('d')
            ->leftJoin('d.user', 'u')->addSelect('u')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();
    }
}
