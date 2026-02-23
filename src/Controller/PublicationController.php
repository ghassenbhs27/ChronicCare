<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Form\PublicationFormType;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use App\Service\AiSummaryService;
use App\Service\FileUploadService;
use App\Service\ForumNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;

#[Route('/publication')]
class PublicationController extends AbstractController
{
    public function __construct(
        private ForumNotificationService $forumNotificationService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'publication_index', methods: ['GET'])]
    public function index(
        Request $request,
        PublicationRepository $repository,
        PaginatorInterface $paginator
    ): Response {
        $categorie = trim((string) $request->query->get('categorie', ''));
        $categorie = in_array($categorie, Publication::CATEGORIES, true) ? $categorie : '';

        $search = trim((string) $request->query->get('search', ''));
        $canSeeDrafts = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_ADMIN');

        $statut = $canSeeDrafts
            ? trim((string) $request->query->get('statut', ''))
            : Publication::STATUT_PUBLIE;

        if ($canSeeDrafts && !in_array($statut, ['', Publication::STATUT_BROUILLON, Publication::STATUT_PUBLIE], true)) {
            $statut = '';
        }

        $qb = $repository->createQueryBuilder('p');

        if ($categorie !== '') {
            $qb->andWhere('p.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        if ($statut !== '') {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        } elseif (!$canSeeDrafts) {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', Publication::STATUT_PUBLIE);
        }

        if ($search !== '') {
            $qb->andWhere('(p.titre LIKE :search OR p.contenu LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('p.createdAt', 'DESC');

        $publications = $paginator->paginate(
            $qb->getQuery(),
            max(1, $request->query->getInt('page', 1)),
            12
        );

        return $this->render('publication/index.html.twig', [
            'publications' => $publications,
            'categorie' => $categorie,
            'statut' => $statut,
            'search' => $search,
            'can_see_drafts' => $canSeeDrafts,
        ]);
    }

    #[Route('/show/{id}', name: 'publication_show', requirements: ['id' => '\\d+'])]
    public function show(
        int $id,
        Request $request,
        PublicationRepository $repository,
        CommentaireRepository $commentaireRepository,
        EntityManagerInterface $em
    ): Response {
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        $canSeeDrafts = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_ADMIN');
        if ($publication->getStatut() === Publication::STATUT_BROUILLON && !$canSeeDrafts) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        // Increment view count only if the current user is not the author
        $currentUser = $this->getUser();
        if ($currentUser !== $publication->getAuteur()) {
            $publication->setNbVues($publication->getNbVues() + 1);
            $em->flush();
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 7;

        // Get paginated top-level comments with their replies
        $commentaires = $commentaireRepository->findByPublicationWithReplies($id, $page, $limit);
        
        // Count only top-level comments for pagination
        $totalTopLevelComments = $commentaireRepository->countByPublication($id);
        
        // Count all comments (including replies) for display
        $totalCommentaires = $commentaireRepository->countAllByPublication($id);
        
        $totalPages = max(1, (int) ceil($totalTopLevelComments / $limit));
        $canComment = $publication->getStatut() === Publication::STATUT_PUBLIE;

        return $this->render('publication/show.html.twig', [
            'publication' => $publication,
            'commentaires' => $commentaires,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCommentaires' => $totalCommentaires,
            'can_comment' => $canComment,
        ]);
    }

    #[Route('/new', name: 'publication_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        FileUploadService $fileUploadService
    ): Response {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent publier. Les patients peuvent commenter les publications.');
        }

        $publication = new Publication();
        $form = $this->createForm(PublicationFormType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publication->setAuteur($this->getUser());

            // Handle file upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $filename = $fileUploadService->uploadFile($imageFile);
                $publication->setImageUrl($filename);
            }

            $em->persist($publication);
            $em->flush();

            $this->addFlash('success', 'Publication créée avec succès !');
            return $this->redirectToRoute('publication_index');
        }

        return $this->render('publication/form.html.twig', [
            'form' => $form,
            'publication' => $publication,
        ]);
    }

    #[Route('/edit/{id}', name: 'publication_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        PublicationRepository $repository,
        EntityManagerInterface $em,
        FileUploadService $fileUploadService
    ): Response {
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        // ✅ FIX: Check if user is the author OR has admin/coach role
        $isAuthor = $this->getUser() && $publication->getAuteur() && $publication->getAuteur() === $this->getUser();
        $hasAdminRole = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_ADMIN');
        
        if (!$isAuthor && !$hasAdminRole) {
            throw new AccessDeniedException('Vous ne pouvez modifier que vos propres publications.');
        }

        $form = $this->createForm(PublicationFormType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                // Delete old image if exists
                if ($publication->getImageUrl()) {
                    $fileUploadService->deleteFile($publication->getImageUrl());
                }
                $filename = $fileUploadService->uploadFile($imageFile);
                $publication->setImageUrl($filename);
            }

            $em->flush();

            $this->addFlash('success', 'Publication modifiee avec succes !');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        return $this->render('publication/form.html.twig', [
            'form' => $form,
            'publication' => $publication,
        ]);
    }

    #[Route('/delete/{id}', name: 'publication_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        PublicationRepository $repository,
        EntityManagerInterface $em,
        FileUploadService $fileUploadService
    ): Response {
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        // ✅ FIX: Check if user is the author OR has admin/coach role
        $isAuthor = $this->getUser() && $publication->getAuteur() && $publication->getAuteur() === $this->getUser();
        $hasAdminRole = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_ADMIN');
        
        if (!$isAuthor && !$hasAdminRole) {
            throw new AccessDeniedException('Vous ne pouvez supprimer que vos propres publications.');
        }

        if (!$this->isCsrfTokenValid('delete_' . $publication->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        // Delete image file if exists
        if ($publication->getImageUrl()) {
            $fileUploadService->deleteFile($publication->getImageUrl());
        }

        $em->remove($publication);
        $em->flush();

        $this->addFlash('success', 'Publication supprimee avec succes !');
        return $this->redirectToRoute('publication_index');
    }

    #[Route('/like/{id}', name: 'publication_like', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function like(
        int $id,
        Request $request,
        PublicationRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $publication = $repository->find($id);

        if (!$publication) {
            return $this->json(['success' => false, 'error' => 'Publication non trouvee'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'error' => 'Requete invalide'], 400);
        }

        $action = $data['action'] ?? 'like';
        if (!in_array($action, ['like', 'unlike'], true)) {
            return $this->json(['success' => false, 'error' => 'Action invalide'], 400);
        }

        if ($action === 'like') {
            $publication->incrementLikes();
            
            // 🔔 Créer une notification pour l'auteur de la publication
            if ($this->getUser()) {
                $this->forumNotificationService->notifyPublicationLike($publication, $this->getUser());
            }
        } else {
            $publication->decrementLikes();
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'likes' => $publication->getNbLikes(),
            'action' => $action,
        ]);
    }

    /**
     * 🤖 Génère un résumé IA avec support multilingue (FR/EN)
     * ✅ ACCÈS: Tous les utilisateurs connectés (ROLE_PATIENT inclus)
     * ✅ FIX: Meilleure gestion des erreurs et détection de la langue
     */
    #[Route('/generate-summary/{id}', name: 'publication_generate_summary', methods: ['POST'])]
    public function generateSummary(
        int $id,
        Request $request,
        PublicationRepository $repository,
        AiSummaryService $aiSummaryService,
        EntityManagerInterface $em
    ): Response {
        // ✅ FIX: Tous les utilisateurs connectés peuvent générer des résumés
        if (!$this->getUser()) {
            $this->logger->warning('❌ Tentative de génération de résumé sans authentification', [
                'publication_id' => $id,
                'ip' => $request->getClientIp(),
            ]);
            return $this->json(['success' => false, 'error' => 'Vous devez être connecté'], 403);
        }

        $publication = $repository->find($id);
        if (!$publication) {
            $this->logger->error('❌ Publication non trouvée', ['id' => $id]);
            return $this->json(['success' => false, 'error' => 'Publication non trouvée'], 404);
        }

        // ✅ FIX: Lecture correcte du paramètre de langue depuis l'URL
        $language = strtolower(trim($request->query->get('lang', 'fr')));
        if (!in_array($language, ['fr', 'en'], true)) {
            $this->logger->warning('⚠️  Langue invalide, utilisation du français par défaut', [
                'requested' => $language,
                'using' => 'fr',
            ]);
            $language = 'fr';
        }

        $this->logger->info('🤖 Demande de génération de résumé', [
            'publication_id' => $id,
            'language' => $language,
            'user' => $this->getUser()->getUserIdentifier(),
            'content_length' => mb_strlen($publication->getContenu()),
        ]);

        // Vérifier que le contenu est assez long
        if (!$aiSummaryService->canGenerateSummary($publication->getContenu())) {
            $this->logger->warning('❌ Contenu trop court pour générer un résumé', [
                'length' => mb_strlen($publication->getContenu()),
                'minimum' => 100,
            ]);
            return $this->json([
                'success' => false,
                'error' => 'Le contenu est trop court (minimum 100 caractères)',
            ], 400);
        }

        // Tenter de générer le résumé avec l'API
        $this->logger->info("📡 Appel à l'API Groq pour générer un résumé en $language");
        $summary = $aiSummaryService->generateSummary($publication->getContenu(), $language);

        $usedFallback = false;
        
        // Si l'API a échoué, utiliser le fallback
        if ($summary === null) {
            $this->logger->warning('⚠️  L\'API Groq a échoué, utilisation du mode de secours', [
                'language' => $language,
            ]);
            $summary = $aiSummaryService->generateFallbackSummary($publication->getContenu(), $language);
            $usedFallback = true;
        }

        // Sauvegarder le résumé
        try {
            $publication->setResume($summary);
            $em->flush();
            
            $this->logger->info('✅ Résumé généré et sauvegardé avec succès', [
                'publication_id' => $id,
                'language' => $language,
                'fallback' => $usedFallback,
                'summary_length' => mb_strlen($summary),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur lors de la sauvegarde du résumé', [
                'error' => $e->getMessage(),
                'publication_id' => $id,
            ]);
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la sauvegarde du résumé',
            ], 500);
        }

        // Messages adaptés à la langue
        $successMessage = $language === 'fr' 
            ? '✅ Résumé français généré avec succès!' 
            : '✅ English summary generated successfully!';
        
        if ($usedFallback) {
            $this->logger->warning('⚠️  ATTENTION: Le mode fallback ne traduit PAS. Le résumé est dans la langue du texte original.');
            $successMessage = $language === 'fr'
                ? '⚠️ Résumé généré en mode secours (l\'API Groq n\'a pas répondu). ATTENTION: Le résumé est dans la langue du texte original, pas traduit.'
                : '⚠️ Summary generated in fallback mode (Groq API did not respond). WARNING: Summary is in the original text language, not translated.';
        }

        return $this->json([
            'success' => true,
            'summary' => $summary,
            'fallback' => $usedFallback,
            'language' => $language,
            'message' => $successMessage,
        ]);
    }
}