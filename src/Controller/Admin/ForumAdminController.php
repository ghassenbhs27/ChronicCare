<?php

namespace App\Controller\Admin;

use App\Entity\Publication;
use App\Entity\Commentaire;
use App\Repository\PublicationRepository;
use App\Repository\CommentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/forum')]
class ForumAdminController extends AbstractController
{
    #[Route('/', name: 'admin_forum_dashboard')]
    public function dashboard(
        PublicationRepository $pubRepo,
        CommentaireRepository $comRepo
    ): Response {
        // Statistics
        $totalPublications = $pubRepo->count([]);
        $totalComments = $comRepo->count([]);
        $publishedCount = $pubRepo->count(['statut' => Publication::STATUT_PUBLIE]);
        $draftCount = $pubRepo->count(['statut' => Publication::STATUT_BROUILLON]);
        
        // Count hidden publications (if using a separate field)
        // $hiddenCount = $pubRepo->count(['isHidden' => true]);
        
        // Activity by period
        $statsDaily = $this->getActivityStats($pubRepo, $comRepo, 1);
        $statsWeekly = $this->getActivityStats($pubRepo, $comRepo, 7);
        
        // Chart data - last 7 days
        $chartData = $this->getChartData($pubRepo, $comRepo);
        
        // Recent publications
        $recentPublications = $pubRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        // Recent comments
        $recentComments = $comRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        // Top publications by views
        $topByViews = $pubRepo->findBy([], ['nbVues' => 'DESC'], 5);
        
        // Top publications by likes
        $topByLikes = $pubRepo->findBy([], ['nbLikes' => 'DESC'], 5);

        return $this->render('admin/forum/dashboard.html.twig', [
            'totalPublications' => $totalPublications,
            'totalComments' => $totalComments,
            'publishedCount' => $publishedCount,
            'draftCount' => $draftCount,
            'statsDaily' => $statsDaily,
            'statsWeekly' => $statsWeekly,
            'chartLabels' => $chartData['labels'],
            'chartPublications' => $chartData['publications'],
            'chartComments' => $chartData['comments'],
            'recentPublications' => $recentPublications,
            'recentComments' => $recentComments,
            'topByViews' => $topByViews,
            'topByLikes' => $topByLikes,
        ]);
    }

    #[Route('/publications', name: 'admin_forum_publications')]
    public function publications(
        Request $request,
        PublicationRepository $repo
    ): Response {
        $status = $request->query->get('status', 'all');
        $category = $request->query->get('category', 'all');
        $search = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'createdAt');
        $dir = $request->query->get('dir', 'DESC');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        
        $qb = $repo->createQueryBuilder('p')
            ->leftJoin('p.auteur', 'a')
            ->leftJoin('p.commentaires', 'c');
        
        // Filters
        if ($status !== 'all') {
            if ($status === 'pending') {
                // Pending = brouillon
                $qb->andWhere('p.statut = :status')
                   ->setParameter('status', Publication::STATUT_BROUILLON);
            } elseif ($status === 'published') {
                $qb->andWhere('p.statut = :status')
                   ->setParameter('status', Publication::STATUT_PUBLIE);
            } elseif ($status === 'hidden') {
                // If you have a separate isHidden field, use it
                // $qb->andWhere('p.isHidden = true');
                // Otherwise, treat 'brouillon' as hidden
                $qb->andWhere('p.statut = :status')
                   ->setParameter('status', Publication::STATUT_BROUILLON);
            }
        }
        
        if ($category !== 'all' && $category !== '') {
            $qb->andWhere('p.categorie = :category')
               ->setParameter('category', $category);
        }
        
        if ($search) {
            $qb->andWhere('p.titre LIKE :search OR p.contenu LIKE :search OR a.nom LIKE :search OR a.prenom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Date filters
        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTimeImmutable($dateFrom);
                $qb->andWhere('p.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFromObj->setTime(0, 0, 0));
            } catch (\Exception $e) {}
        }
        
        if ($dateTo) {
            try {
                $dateToObj = new \DateTimeImmutable($dateTo);
                $qb->andWhere('p.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateToObj->setTime(23, 59, 59));
            } catch (\Exception $e) {}
        }
        
        // Sorting
        $validSorts = ['createdAt', 'titre', 'nbVues', 'nbLikes'];
        if (!in_array($sort, $validSorts)) {
            $sort = 'createdAt';
        }
        $qb->orderBy('p.' . $sort, $dir);
        
        $publications = $qb->getQuery()->getResult();
        
        // Statistics
        $totalPublications = count($publications);
        $totalViews = array_sum(array_map(fn($p) => $p->getNbVues(), $publications));
        $totalLikes = array_sum(array_map(fn($p) => $p->getNbLikes(), $publications));
        $totalComments = array_sum(array_map(fn($p) => $p->getCommentaires()->count(), $publications));
        
        return $this->render('admin/forum/publications.html.twig', [
            'publications' => $publications,
            'status' => $status,
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'categories' => Publication::CATEGORIES,
            'totalPublications' => $totalPublications,
            'totalViews' => $totalViews,
            'totalLikes' => $totalLikes,
            'totalComments' => $totalComments,
        ]);
    }

    #[Route('/publications/{id}/toggle-visibility', name: 'admin_forum_publication_toggle', methods: ['POST'])]
    public function togglePublicationVisibility(
        Publication $publication,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('toggle_publication_' . $publication->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_forum_publications');
        }
        
        // Toggle between published and hidden (brouillon)
        $newStatus = $publication->getStatut() === Publication::STATUT_PUBLIE 
            ? Publication::STATUT_BROUILLON 
            : Publication::STATUT_PUBLIE;
        
        $publication->setStatut($newStatus);
        $em->flush();
        
        $message = $newStatus === Publication::STATUT_PUBLIE 
            ? 'Publication publiée avec succès.' 
            : 'Publication masquée avec succès.';
        
        $this->addFlash('success', $message);
        
        return $this->redirectToRoute('admin_forum_publications');
    }

    #[Route('/publications/{id}/delete', name: 'admin_forum_publication_delete', methods: ['POST'])]
    public function deletePublication(
        Request $request,
        Publication $publication,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_publication_' . $publication->getId(), $request->request->get('_token'))) {
            $titre = $publication->getTitre();
            $em->remove($publication);
            $em->flush();
            
            $this->addFlash('success', "Publication « {$titre} » supprimée avec succès.");
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }
        
        return $this->redirectToRoute('admin_forum_publications');
    }

    #[Route('/publications/{id}', name: 'admin_forum_publication_show')]
    public function showPublication(Publication $publication): Response
    {
        return $this->render('admin/forum/publication_show.html.twig', [
            'publication' => $publication,
        ]);
    }

    #[Route('/comments', name: 'admin_forum_comments')]
    public function comments(
        Request $request,
        CommentaireRepository $repo,
        PublicationRepository $pubRepo
    ): Response {
        $publicationId = $request->query->get('publication', 'all');
        $search = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'createdAt');
        $dir = $request->query->get('dir', 'DESC');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        
        $qb = $repo->createQueryBuilder('c')
            ->leftJoin('c.publication', 'p')
            ->leftJoin('c.auteur', 'a');
        
        // Filter by publication
        if ($publicationId !== 'all' && is_numeric($publicationId)) {
            $qb->andWhere('p.id = :pubId')
               ->setParameter('pubId', $publicationId);
        }
        
        // Search
        if ($search) {
            $qb->andWhere('c.contenu LIKE :search OR a.nom LIKE :search OR a.prenom LIKE :search OR p.titre LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Date filters
        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTimeImmutable($dateFrom);
                $qb->andWhere('c.createdAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFromObj->setTime(0, 0, 0));
            } catch (\Exception $e) {}
        }
        
        if ($dateTo) {
            try {
                $dateToObj = new \DateTimeImmutable($dateTo);
                $qb->andWhere('c.createdAt <= :dateTo')
                   ->setParameter('dateTo', $dateToObj->setTime(23, 59, 59));
            } catch (\Exception $e) {}
        }
        
        // Sorting
        $validSorts = ['createdAt', 'likeCount'];
        if (!in_array($sort, $validSorts)) {
            $sort = 'createdAt';
        }
        $qb->orderBy('c.' . $sort, $dir);
        
        $commentaires = $qb->getQuery()->getResult();
        
        // Get publications for filter dropdown
        $publications = $pubRepo->findBy([], ['titre' => 'ASC']);
        
        // Statistics
        $totalComments = count($commentaires);
        $totalLikes = array_sum(array_map(fn($c) => $c->getLikeCount(), $commentaires));
        
        return $this->render('admin/forum/comments.html.twig', [
            'commentaires' => $commentaires,
            'publications' => $publications,
            'publicationId' => $publicationId,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalComments' => $totalComments,
            'totalLikes' => $totalLikes,
        ]);
    }

    #[Route('/comments/{id}/delete', name: 'admin_forum_comment_delete', methods: ['POST'])]
    public function deleteComment(
        Request $request,
        Commentaire $commentaire,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_comment_' . $commentaire->getId(), $request->request->get('_token'))) {
            $em->remove($commentaire);
            $em->flush();
            
            $this->addFlash('success', 'Commentaire supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }
        
        return $this->redirectToRoute('admin_forum_comments');
    }

    #[Route('/ranking', name: 'admin_forum_ranking')]
    public function ranking(
        Request $request,
        PublicationRepository $repo
    ): Response {
        $period = $request->query->get('period', 'all');
        $type = $request->query->get('type', 'views');
        $limit = (int) $request->query->get('limit', 20);
        
        // Determine date filter
        $since = null;
        switch ($period) {
            case 'day':
                $since = new \DateTimeImmutable('-1 day');
                break;
            case 'week':
                $since = new \DateTimeImmutable('-7 days');
                break;
            case 'month':
                $since = new \DateTimeImmutable('-30 days');
                break;
            case 'year':
                $since = new \DateTimeImmutable('-1 year');
                break;
        }
        
        // Get rankings based on type
        $rankings = match($type) {
            'views' => $repo->findMostViewed($limit, $since),
            'likes' => $repo->findMostLiked($limit, $since),
            'comments' => $repo->findMostCommented($limit, $since),
            'trending' => $repo->findTrending($limit, $period === 'week' ? 7 : 30),
            default => $repo->findMostViewed($limit, $since),
        };
        
        // Get statistics
        $stats = $repo->getRankingStats();
        
        // Get top authors
        $topAuthors = $repo->getTopAuthors(5);
        
        // Get top by category
        $topByCategory = [];
        foreach (Publication::CATEGORIES as $category) {
            $topByCategory[$category] = $repo->findTopByCategory($category, 3);
        }
        
        return $this->render('admin/forum/ranking.html.twig', [
            'rankings' => $rankings,
            'period' => $period,
            'type' => $type,
            'limit' => $limit,
            'stats' => $stats,
            'topAuthors' => $topAuthors,
            'topByCategory' => $topByCategory,
            'categories' => Publication::CATEGORIES,
        ]);
    }

    #[Route('/api/stats', name: 'admin_forum_api_stats', methods: ['GET'])]
    public function apiStats(
        PublicationRepository $pubRepo,
        CommentaireRepository $comRepo
    ): JsonResponse {
        $totalPublications = $pubRepo->count([]);
        $totalComments = $comRepo->count([]);
        $statsDaily = $this->getActivityStats($pubRepo, $comRepo, 1);
        $chartData = $this->getChartData($pubRepo, $comRepo);
        
        return $this->json([
            'totalPublications' => $totalPublications,
            'totalComments' => $totalComments,
            'statsDaily' => $statsDaily,
            'chartLabels' => $chartData['labels'],
            'chartPublications' => $chartData['publications'],
            'chartComments' => $chartData['comments'],
        ]);
    }

    private function getActivityStats(
        PublicationRepository $pubRepo,
        CommentaireRepository $comRepo,
        int $days
    ): array {
        $date = new \DateTimeImmutable("-{$days} days");
        
        $publications = $pubRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
        
        $comments = $comRepo->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
        
        return [
            'publications' => (int) $publications,
            'comments' => (int) $comments,
        ];
    }

    private function getChartData(
        PublicationRepository $pubRepo,
        CommentaireRepository $comRepo
    ): array {
        $labels = [];
        $publications = [];
        $comments = [];
        
        $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-{$i} days");
            $startOfDay = $date->setTime(0, 0, 0);
            $endOfDay = $date->setTime(23, 59, 59);
            
            $labels[] = $dayNames[(int) $date->format('N') - 1];
            
            $pubCount = $pubRepo->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.createdAt >= :start')
                ->andWhere('p.createdAt <= :end')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay)
                ->getQuery()
                ->getSingleScalarResult();
            
            $comCount = $comRepo->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.createdAt >= :start')
                ->andWhere('c.createdAt <= :end')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay)
                ->getQuery()
                ->getSingleScalarResult();
            
            $publications[] = (int) $pubCount;
            $comments[] = (int) $comCount;
        }
        
        return [
            'labels' => $labels,
            'publications' => $publications,
            'comments' => $comments,
        ];
    }
}