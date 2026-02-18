<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationView;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PublicationViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationView::class);
    }

    public function hasUserViewed(User $user, Publication $publication): bool
    {
        return (bool) $this->findOneBy([
            'user' => $user,
            'publication' => $publication,
        ]);
    }
}
