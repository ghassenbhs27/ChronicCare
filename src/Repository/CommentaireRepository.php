<?php

namespace App\Repository;

use App\Entity\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commentaire>
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    /**
     * Find paginated top-level comments (no parent) for a publication
     * @return Commentaire[]
     */
    public function findByPublicationPaginated(int $publicationId, int $page = 1, int $limit = 7): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->where('c.publication = :publication')
            ->andWhere('c.parent IS NULL') // Only top-level comments
            ->setParameter('publication', $publicationId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count only top-level comments for a publication
     */
    public function countByPublication(int $publicationId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.publication = :publication')
            ->andWhere('c.parent IS NULL') // Only count top-level comments
            ->setParameter('publication', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all replies for a specific comment (ordered by date)
     * @return Commentaire[]
     */
    public function findRepliesByParent(int $parentId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parentId)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total replies for a comment
     */
    public function countRepliesByParent(int $parentId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parentId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find all comments (top-level + replies) for a publication
     * @return Commentaire[]
     */
    public function findAllByPublication(int $publicationId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.publication = :publication')
            ->setParameter('publication', $publicationId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total comments (including replies) for a publication
     */
    public function countAllByPublication(int $publicationId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.publication = :publication')
            ->setParameter('publication', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find top-level comments with their replies for a publication (optimized with joins)
     * @return Commentaire[]
     */
    public function findByPublicationWithReplies(int $publicationId, int $page = 1, int $limit = 7): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->leftJoin('c.replies', 'r')
            ->addSelect('r')
            ->leftJoin('r.auteur', 'ra')
            ->addSelect('ra')
            ->leftJoin('c.auteur', 'ca')
            ->addSelect('ca')
            ->where('c.publication = :publication')
            ->andWhere('c.parent IS NULL')
            ->setParameter('publication', $publicationId)
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics about comments and replies for admin
     *
     * @return array{total: int, topLevel: int, replies: int}
     */
    public function getCommentStats(): array
    {
        $qb = $this->createQueryBuilder('c');

        /** @var string|int $total */
        $total = $qb->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var string|int $topLevel */
        $topLevel = $qb->select('COUNT(c.id)')
            ->where('c.parent IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var string|int $replies */
        $replies = $qb->select('COUNT(c.id)')
            ->where('c.parent IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'topLevel' => (int) $topLevel,
            'replies' => (int) $replies,
        ];
    }
}