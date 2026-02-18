<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationLike>
 */
class PublicationLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationLike::class);
    }

    public function findByUserAndPublication(User $user, Publication $publication): ?PublicationLike
    {
        return $this->findOneBy([
            'user' => $user,
            'publication' => $publication,
        ]);
    }

    public function countByPublication(Publication $publication): int
    {
        return $this->count(['publication' => $publication]);
    }

    public function hasUserLiked(User $user, Publication $publication): bool
    {
        return $this->findByUserAndPublication($user, $publication) !== null;
    }
}
