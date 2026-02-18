<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Publication;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
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
    #[Route('/add/{publicationId}', name: 'commentaire_add', requirements: ['publicationId' => '\\d+'], methods: ['POST'])]
    public function add(
        int $publicationId,
        Request $request,
        PublicationRepository $publicationRepository,
        EntityManagerInterface $em,
        ValidatorInterface $validator
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

        if ($this->getUser()) {
            $commentaire->setAuteur($this->getUser());
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

        $this->addFlash('success', 'Commentaire ajoute avec succes !');
        return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
    }

    #[Route('/edit/{id}', name: 'commentaire_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
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

        $isAuthor = $this->getUser() && $commentaire->getAuteur() && $commentaire->getAuteur()->getId() === $this->getUser()->getId();
        if (!$isAuthor && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
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
                $this->generateUrl('publication_show', ['id' => $publication->getId()]) . '#comments'
            );
        }

        return $this->render('commentaire/edit.html.twig', [
            'commentaire' => $commentaire,
            'publication' => $publication,
        ]);
    }

    #[Route('/delete/{id}', name: 'commentaire_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        CommentaireRepository $repository,
        EntityManagerInterface $em
    ): Response {
        $commentaire = $repository->find($id);

        if (!$commentaire) {
            throw $this->createNotFoundException('Commentaire non trouve');
        }

        $isAuthor = $this->getUser() && $commentaire->getAuteur() && $commentaire->getAuteur()->getId() === $this->getUser()->getId();
        if (!$isAuthor && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Vous ne pouvez supprimer que vos propres commentaires.');
        }

        if (!$this->isCsrfTokenValid('delete_commentaire_' . $commentaire->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('publication_show', ['id' => $commentaire->getPublication()->getId()]);
        }

        $publicationId = $commentaire->getPublication()->getId();

        $em->remove($commentaire);
        $em->flush();

        $this->addFlash('success', 'Commentaire supprime avec succes !');
        return $this->redirectToRoute('publication_show', ['id' => $publicationId]);
    }

    #[Route('/like/{id}', name: 'commentaire_like', requirements: ['id' => '\\d+'], methods: ['POST'])]
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
