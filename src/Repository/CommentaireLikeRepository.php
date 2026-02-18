<?php

namespace App\Repository;

use App\Entity\Commentaire;
use App\Entity\CommentaireLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentaireLike>
 */
class CommentaireLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentaireLike::class);
    }

    public function findByUserAndCommentaire(User $user, Commentaire $commentaire): ?CommentaireLike
    {
        return $this->findOneBy([
            'user' => $user,
            'commentaire' => $commentaire,
        ]);
    }

    public function hasUserLiked(User $user, Commentaire $commentaire): bool
    {
        return $this->findByUserAndCommentaire($user, $commentaire) !== null;
    }

    /**
     * Returns an array of commentaire IDs that the user has liked.
     */
    public function findLikedCommentaireIds(User $user, array $commentaireIds): array
    {
        if (empty($commentaireIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('cl')
            ->select('IDENTITY(cl.commentaire) as commentaireId')
            ->where('cl.user = :user')
            ->andWhere('cl.commentaire IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $commentaireIds)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'commentaireId');
    }
}
