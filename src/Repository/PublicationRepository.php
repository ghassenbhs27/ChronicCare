<?php

namespace App\Repository;

use App\Entity\Publication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Publication[]
     */
    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> */
    public function countByStatut(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.statut AS statut', 'COUNT(p.id) AS total')
            ->groupBy('p.statut')
            ->getQuery()
            ->getArrayResult();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['statut']] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Find most popular publications by views
     * @return Publication[]
     */
    public function findMostViewed(int $limit = 10, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('c')
            ->where('p.statut = :statut')
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->orderBy('p.nbVues', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('p.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find most liked publications
     * @return Publication[]
     */
    public function findMostLiked(int $limit = 10, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('c')
            ->where('p.statut = :statut')
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->orderBy('p.nbLikes', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('p.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find most commented publications
     * @return Publication[]
     */
    public function findMostCommented(int $limit = 10, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('COUNT(c.id) as HIDDEN commentCount')
            ->where('p.statut = :statut')
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->groupBy('p.id')
            ->orderBy('commentCount', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('p.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find trending publications (based on popularity score)
     * Score = (views * 1) + (likes * 3) + (comments * 5)
     * @return Publication[]
     */
    public function findTrending(int $limit = 10, int $days = 7): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('c')
            ->addSelect('(p.nbVues * 1 + p.nbLikes * 3 + SIZE(p.commentaires) * 5) as HIDDEN popularityScore')
            ->where('p.statut = :statut')
            ->andWhere('p.createdAt >= :since')
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->setParameter('since', $since)
            ->orderBy('popularityScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find publications by category with stats
     * @return Publication[]
     */
    public function findTopByCategory(string $category, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->addSelect('a')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('c')
            ->where('p.categorie = :category')
            ->andWhere('p.statut = :statut')
            ->setParameter('category', $category)
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->addSelect('(p.nbVues * 1 + p.nbLikes * 3 + SIZE(p.commentaires) * 5) as HIDDEN score')
            ->orderBy('score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get ranking statistics for all publications
     * @return array
     */
    public function getRankingStats(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('
                COUNT(p.id) as totalPublications,
                SUM(p.nbVues) as totalViews,
                SUM(p.nbLikes) as totalLikes,
                AVG(p.nbVues) as avgViews,
                AVG(p.nbLikes) as avgLikes
            ')
            ->where('p.statut = :statut')
            ->setParameter('statut', Publication::STATUT_PUBLIE);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'totalPublications' => (int) ($result['totalPublications'] ?? 0),
            'totalViews' => (int) ($result['totalViews'] ?? 0),
            'totalLikes' => (int) ($result['totalLikes'] ?? 0),
            'avgViews' => round((float) ($result['avgViews'] ?? 0), 1),
            'avgLikes' => round((float) ($result['avgLikes'] ?? 0), 1),
        ];
    }

    /**
     * Get top authors by total engagement
     * @return array
     */
    public function getTopAuthors(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->select('
                a.id,
                a.nom,
                a.prenom,
                COUNT(p.id) as publicationCount,
                SUM(p.nbVues) as totalViews,
                SUM(p.nbLikes) as totalLikes
            ')
            ->leftJoin('p.auteur', 'a')
            ->where('p.statut = :statut')
            ->andWhere('a.id IS NOT NULL')
            ->setParameter('statut', Publication::STATUT_PUBLIE)
            ->groupBy('a.id')
            ->orderBy('totalViews', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Publication[] Returns an array of Publication objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Publication
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}