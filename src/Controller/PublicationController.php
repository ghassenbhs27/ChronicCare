<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Repository\CommentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/publication')]
class PublicationController extends AbstractController
{
    #[Route('', name: 'publication_index', methods: ['GET'])]
    public function index(
        Request $request,
        PublicationRepository $repository,
        PaginatorInterface $paginator
    ): Response {
        $categorie = $request->query->get('categorie');
        $search = trim((string) $request->query->get('search', ''));
        $canSeeDrafts = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_ADMIN');
        $statut = $canSeeDrafts ? $request->query->get('statut', '') : 'publie';

        $qb = $repository->createQueryBuilder('p');

        if ($categorie) {
            $qb->andWhere('p.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        if ($statut !== '') {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', $statut);
        } elseif (!$canSeeDrafts) {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', 'publie');
        }

        if ($search !== '') {
            $qb->andWhere('(p.titre LIKE :search OR p.contenu LIKE :search)')
               ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('p.createdAt', 'DESC');

        $publications = $paginator->paginate(
            $qb->getQuery(),
            $request->query->getInt('page', 1),
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

    #[Route('/show/{id}', name: 'publication_show', requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        Request $request,
        PublicationRepository $repository,
        CommentaireRepository $commentaireRepository,
        EntityManagerInterface $em
    ): Response {
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvée');
        }

        $canSeeDrafts = $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_ADMIN');
        if ($publication->getStatut() === 'brouillon' && !$canSeeDrafts) {
            throw $this->createNotFoundException('Publication non trouvée');
        }

        $publication->setNbVues($publication->getNbVues() + 1);
        $em->flush();

        $page = $request->query->getInt('page', 1);
        $limit = 7;

        $commentaires = $commentaireRepository->findByPublicationPaginated($id, $page, $limit);
        $totalCommentaires = $commentaireRepository->countByPublication($id);
        $totalPages = (int) ceil($totalCommentaires / $limit);
        $canComment = $publication->getStatut() === 'publie';

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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent publier. Les patients peuvent commenter les publications.');
        }
        $publication = new Publication();
        $publication->setTitre('');
        $publication->setContenu('');
        $publication->setCategorie('nutrition');
        $publication->setStatut('brouillon');

        if ($request->isMethod('POST')) {
            $publication->setTitre($request->request->get('titre'));
            $publication->setContenu($request->request->get('contenu'));
            $publication->setCategorie($request->request->get('categorie'));
            $publication->setStatut($request->request->get('statut', 'brouillon'));
            if ($this->getUser()) {
                $publication->setAuteur($this->getUser());
            }

            $em->persist($publication);
            $em->flush();

            $this->addFlash('success', 'Publication créée avec succès !');
            return $this->redirectToRoute('publication_index');
        }

        return $this->render('publication/form.html.twig', [
            'publication' => $publication,
        ]);
    }

    #[Route('/edit/{id}', name: 'publication_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, PublicationRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent modifier une publication.');
        }
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $publication->setTitre($request->request->get('titre'));
            $publication->setContenu($request->request->get('contenu'));
            $publication->setCategorie($request->request->get('categorie'));
            $publication->setStatut($request->request->get('statut'));

            $em->flush();

            $this->addFlash('success', 'Publication modifiée avec succès !');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        return $this->render('publication/form.html.twig', [
            'publication' => $publication,
        ]);
    }

    #[Route('/delete/{id}', name: 'publication_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, PublicationRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent supprimer une publication.');
        }
        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException();
        }

        $em->remove($publication);
        $em->flush();

        $this->addFlash('success', 'Publication supprimée avec succès !');
        return $this->redirectToRoute('publication_index');
    }

    #[Route('/like/{id}', name: 'publication_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function like(int $id, Request $request, PublicationRepository $repository, EntityManagerInterface $em): Response
    {
        $publication = $repository->find($id);

        if (!$publication) {
            return $this->json(['success' => false, 'error' => 'Publication non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? 'like';

        if ($action === 'like') {
            $publication->setNbLikes($publication->getNbLikes() + 1);
        } else {
            $currentLikes = $publication->getNbLikes();
            if ($currentLikes > 0) {
                $publication->setNbLikes($currentLikes - 1);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'likes' => $publication->getNbLikes(),
            'action' => $action,
        ]);
    }
}
