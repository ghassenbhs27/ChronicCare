<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Publication;
use App\Entity\User;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use App\Service\CommentTextFormatterService;
use App\Service\ForumNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/commentaire')]
class CommentaireController extends AbstractController
{
    public function __construct(
        private ForumNotificationService $forumNotificationService
    ) {
    }

    #[Route('/add/{publicationId}', name: 'commentaire_add', requirements: ['publicationId' => '\d+'], methods: ['POST'])]
    public function add(
        int $publicationId,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        CommentTextFormatterService $formatterService
    ): Response {
        $publication = $publicationRepository->find($publicationId);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        if (!$this->isCsrfTokenValid('commentaire_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        if ($publication->getStatut() !== Publication::STATUT_PUBLIE) {
            $this->addFlash('error', 'Les commentaires sont desactives pour les publications en brouillon.');
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        $commentaire = new Commentaire();
        $commentaire->setContenu(trim((string) $request->request->get('contenu', '')));
        $commentaire->setPublication($publication);
        $commentaire->setIsAnonymous($request->request->getBoolean('is_anonymous'));

        // Rich text formatting
        $fontStyle = $request->request->get('fontStyle');
        if (is_string($fontStyle) && in_array($fontStyle, Commentaire::FONT_STYLES, true)) {
            $commentaire->setFontStyle($fontStyle);
        }

        $fontSize = $request->request->get('fontSize');
        if (is_string($fontSize) && in_array($fontSize, Commentaire::FONT_SIZES, true)) {
            $commentaire->setFontSize($fontSize);
        }

        $textColor = $request->request->get('textColor');
        if (is_string($textColor) && $textColor !== '') {
            $commentaire->setTextColor($textColor);
        }

        // ✅ PHPStan fix: setAuteur() attend App\Entity\User|null
        $user = $this->getUser();
        if ($user instanceof User) {
            $commentaire->setAuteur($user);
        }

        $violations = $validator->validate($commentaire);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', $violation->getMessage());
            }
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        $em->persist($commentaire);
        $em->flush();

        // 🔔 Notifier l’auteur de la publication
        $this->forumNotificationService->notifyNewCommentaire($commentaire);

        $this->addFlash('success', 'Commentaire ajoute avec succes !');
        return $this->redirect(
            $this->generateUrl('publication_show', ['id' => $publicationId]) . '#comments'
        );
    }

    #[Route('/edit/{id}', name: 'commentaire_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        CommentaireRepository $repository,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            throw $this->createNotFoundException('Commentaire non trouve');
        }

        // ✅ PHPStan-safe author check
        $user = $this->getUser();
        $author = $commentaire->getAuteur();

        $isAuthor =
            ($user instanceof User)
            && ($author instanceof User)
            && ($user->getId() !== null)
            && ($author->getId() !== null)
            && ($user->getId() === $author->getId());

        $hasAdminRole =
            $this->isGranted('ROLE_COACH')
            || $this->isGranted('ROLE_NUTRITIONNISTE')
            || $this->isGranted('ROLE_ADMIN');

        if (!$isAuthor && !$hasAdminRole) {
            throw new AccessDeniedException('Vous ne pouvez modifier que vos propres commentaires.');
        }

        $publication = $commentaire->getPublication();
        if ($publication->getStatut() !== Publication::STATUT_PUBLIE) {
            $this->addFlash('error', 'Les commentaires d\'une publication en brouillon ne peuvent pas etre modifies.');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('commentaire_edit_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('commentaire_edit', ['id' => $id]);
            }

            $commentaire->setContenu(trim((string) $request->request->get('contenu', '')));

            // Update rich text formatting
            $fontStyle = $request->request->get('fontStyle');
            if (is_string($fontStyle) && in_array($fontStyle, Commentaire::FONT_STYLES, true)) {
                $commentaire->setFontStyle($fontStyle);
            }

            $fontSize = $request->request->get('fontSize');
            if (is_string($fontSize) && in_array($fontSize, Commentaire::FONT_SIZES, true)) {
                $commentaire->setFontSize($fontSize);
            }

            $textColor = $request->request->get('textColor');
            if (is_string($textColor) && $textColor !== '') {
                $commentaire->setTextColor($textColor);
            }

            $commentaire->setUpdatedAt(new \DateTimeImmutable());

            $violations = $validator->validate($commentaire);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }
                return $this->redirectToRoute('commentaire_edit', ['id' => $id]);
            }

            $em->flush();
            $this->addFlash('success', 'Commentaire modifie avec succes !');

            return $this->redirect(
                $this->generateUrl('publication_show', ['id' => $publication->getId()]) . '#comment-' . $commentaire->getId()
            );
        }

        return $this->render('commentaire/edit.html.twig', [
            'commentaire' => $commentaire,
            'publication' => $publication,
        ]);
    }

    #[Route('/delete/{id}', name: 'commentaire_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        CommentaireRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            $this->addFlash('error', 'Ce commentaire n\'existe plus ou a déjà été supprimé.');

            $referer = $request->headers->get('referer');
            if (is_string($referer) && preg_match('/\/publication\/show\/(\d+)/', $referer, $matches)) {
                return $this->redirectToRoute('publication_show', ['id' => (int) $matches[1]]);
            }

            return $this->redirectToRoute('publication_index');
        }

        // ✅ PHPStan-safe author check
        $user = $this->getUser();
        $author = $commentaire->getAuteur();

        $isAuthor =
            ($user instanceof User)
            && ($author instanceof User)
            && ($user->getId() !== null)
            && ($author->getId() !== null)
            && ($user->getId() === $author->getId());

        $hasAdminRole =
            $this->isGranted('ROLE_COACH')
            || $this->isGranted('ROLE_NUTRITIONNISTE')
            || $this->isGranted('ROLE_ADMIN');

        if (!$isAuthor && !$hasAdminRole) {
            throw new AccessDeniedException('Vous ne pouvez supprimer que vos propres commentaires.');
        }

        $publicationId = $commentaire->getPublication()->getId();

        if (!$this->isCsrfTokenValid('delete_commentaire_' . $commentaire->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        $em->remove($commentaire);
        $em->flush();

        $this->addFlash('success', 'Commentaire supprime avec succes !');
        return $this->redirect(
            $this->generateUrl('publication_show', ['id' => $publicationId]) . '#comments'
        );
    }

    #[Route('/like/{id}', name: 'commentaire_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function like(
        int $id,
        Request $request,
        CommentaireRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            return $this->json(['success' => false, 'error' => 'Commentaire non trouve'], 404);
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
            $commentaire->incrementLikes();

            // ✅ PHPStan fix: notifyCommentaireLike attend App\Entity\User
            $user = $this->getUser();
            if ($user instanceof User) {
                $this->forumNotificationService->notifyCommentaireLike($commentaire, $user);
            }
        } else {
            $commentaire->decrementLikes();
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'likes' => $commentaire->getLikeCount(),
            'action' => $action,
        ]);
    }

    /**
     * Add a reply to an existing comment
     */
    #[Route('/reply/{parentId}', name: 'commentaire_reply', requirements: ['parentId' => '\d+'], methods: ['POST'])]
    public function reply(
        int $parentId,
        Request $request,
        CommentaireRepository $commentaireRepo,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $parent = $commentaireRepo->find($parentId);

        if (!$parent) {
            throw $this->createNotFoundException('Commentaire parent non trouvé');
        }

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('commentaire_reply', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('publication_show', ['id' => $parent->getPublication()->getId()]);
        }

        if ($parent->getPublication()->getStatut() !== Publication::STATUT_PUBLIE) {
            $this->addFlash('error', 'Les réponses sont désactivées pour les publications en brouillon.');
            return $this->redirectToRoute('publication_show', ['id' => $parent->getPublication()->getId()]);
        }

        $contenu = trim((string) $request->request->get('contenu', ''));

        if (strlen($contenu) < 3) {
            $this->addFlash('error', 'Le commentaire doit contenir au moins 3 caractères');
            return $this->redirectToRoute('publication_show', ['id' => $parent->getPublication()->getId()]);
        }

        $reply = new Commentaire();
        $reply->setPublication($parent->getPublication());
        $reply->setParent($parent);
        $reply->setContenu($contenu);
        $reply->setIsAnonymous($request->request->getBoolean('is_anonymous'));

        // ✅ PHPStan fix: setAuteur attend App\Entity\User|null
        $user = $this->getUser();
        if ($user instanceof User) {
            $reply->setAuteur($user);
        }

        $fontStyle = $request->request->get('fontStyle');
        if (is_string($fontStyle) && in_array($fontStyle, Commentaire::FONT_STYLES, true)) {
            $reply->setFontStyle($fontStyle);
        }

        $fontSize = $request->request->get('fontSize');
        if (is_string($fontSize) && in_array($fontSize, Commentaire::FONT_SIZES, true)) {
            $reply->setFontSize($fontSize);
        }

        $textColor = $request->request->get('textColor');
        if (is_string($textColor) && $textColor !== '') {
            $reply->setTextColor($textColor);
        }

        $violations = $validator->validate($reply);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', $violation->getMessage());
            }
            return $this->redirectToRoute('publication_show', ['id' => $parent->getPublication()->getId()]);
        }

        $em->persist($reply);
        $em->flush();

        $this->forumNotificationService->notifyNewCommentaire($reply);

        $this->addFlash('success', 'Réponse ajoutée avec succès');
        return $this->redirect(
            $this->generateUrl('publication_show', ['id' => $parent->getPublication()->getId()])
            . '#comment-' . $parent->getId()
        );
    }
}