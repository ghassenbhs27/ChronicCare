<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/commentaire')]
class CommentaireController extends AbstractController
{
    #[Route('/add/{publicationId}', name: 'commentaire_add', requirements: ['publicationId' => '\d+'], methods: ['POST'])]
    public function add(
        int $publicationId,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $em
    ): Response {
        $publication = $publicationRepository->find($publicationId);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvée');
        }

        if ($publication->getStatut() !== 'publie') {
            $this->addFlash('error', 'Les commentaires sont désactivés pour les publications en brouillon.');
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        $contenu = $request->request->get('contenu');
        $isAnonymous = $request->request->get('is_anonymous', false);

        if (empty(trim((string) $contenu))) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide');
            return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
        }

        $commentaire = new Commentaire();
        $commentaire->setContenu((string) $contenu);
        $commentaire->setPublication($publication);
        $commentaire->setIsAnonymous((bool) $isAnonymous);
        if ($this->getUser()) {
            $commentaire->setAuteur($this->getUser());
        }

        $em->persist($commentaire);
        $em->flush();

        $this->addFlash('success', 'Commentaire ajouté avec succès !');
        return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
    }

    #[Route('/edit/{id}', name: 'commentaire_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        CommentaireRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        $isAuthor = $this->getUser() && $commentaire->getAuteur() && $commentaire->getAuteur()->getId() === $this->getUser()->getId();
        if (!$isAuthor && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Vous ne pouvez modifier que vos propres commentaires.');
        }

        $publication = $commentaire->getPublication();
        if ($publication->getStatut() !== 'publie') {
            $this->addFlash('error', 'Les commentaires d\'une publication en brouillon ne peuvent pas être modifiés.');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('commentaire_edit_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide.');
                return $this->redirectToRoute('commentaire_edit', ['id' => $id]);
            }
            $contenu = trim((string) $request->request->get('contenu'));
            if ($contenu === '') {
                $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
                return $this->redirectToRoute('commentaire_edit', ['id' => $id]);
            }
            $commentaire->setContenu($contenu);
            $em->flush();
            $this->addFlash('success', 'Commentaire modifié avec succès !');
            return $this->redirect(
                $this->generateUrl('publication_show', ['id' => $publication->getId()]) . '#comments'
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
        CommentaireRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            throw $this->createNotFoundException('Commentaire non trouvé');
        }

        $isAuthor = $this->getUser() && $commentaire->getAuteur() && $commentaire->getAuteur()->getId() === $this->getUser()->getId();
        if (!$isAuthor && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Vous ne pouvez supprimer que vos propres commentaires.');
        }

        $publicationId = $commentaire->getPublication()->getId();

        $em->remove($commentaire);
        $em->flush();

        $this->addFlash('success', 'Commentaire supprimé avec succès !');
        return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
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
            return $this->json(['success' => false, 'error' => 'Commentaire non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? 'like';

        if ($action === 'like') {
            $commentaire->incrementLikes();
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
}
