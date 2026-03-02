<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/publication')]
class PublicationController extends AbstractController
{
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

        $publication->setNbVues($publication->getNbVues() + 1);
        $em->flush();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 7;

        $commentaires = $commentaireRepository->findByPublicationPaginated($id, $page, $limit);
        $totalCommentaires = $commentaireRepository->countByPublication($id);
        $totalPages = max(1, (int) ceil($totalCommentaires / $limit));
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
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent publier. Les patients peuvent commenter les publications.');
        }

        $publication = new Publication();
        $publication->setTitre('');
        $publication->setContenu('');
        $publication->setCategorie(Publication::CATEGORIES[0]);
        $publication->setStatut(Publication::STATUT_BROUILLON);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('publication_form', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('publication_new');
            }

            $publication->setTitre(trim((string) $request->request->get('titre', '')));
            $publication->setContenu(trim((string) $request->request->get('contenu', '')));

            $categorie = trim((string) $request->request->get('categorie', ''));
            $publication->setCategorie(in_array($categorie, Publication::CATEGORIES, true) ? $categorie : null);

            $statut = trim((string) $request->request->get('statut', Publication::STATUT_BROUILLON));
            $publication->setStatut(in_array($statut, [Publication::STATUT_BROUILLON, Publication::STATUT_PUBLIE], true) ? $statut : Publication::STATUT_BROUILLON);

            $user = $this->getUser();
            if ($user instanceof User) {
                $publication->setAuteur($user);
            }

            $violations = $validator->validate($publication);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }

                return $this->render('publication/form.html.twig', [
                    'publication' => $publication,
                ]);
            }

            $em->persist($publication);
            $em->flush();

            $this->addFlash('success', 'Publication creee avec succes !');
            return $this->redirectToRoute('publication_index');
        }

        return $this->render('publication/form.html.twig', [
            'publication' => $publication,
        ]);
    }

    #[Route('/edit/{id}', name: 'publication_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        PublicationRepository $repository,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent modifier une publication.');
        }

        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('publication_form', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('publication_edit', ['id' => $publication->getId()]);
            }

            $publication->setTitre(trim((string) $request->request->get('titre', '')));
            $publication->setContenu(trim((string) $request->request->get('contenu', '')));

            $categorie = trim((string) $request->request->get('categorie', ''));
            $publication->setCategorie(in_array($categorie, Publication::CATEGORIES, true) ? $categorie : null);

            $statut = trim((string) $request->request->get('statut', Publication::STATUT_BROUILLON));
            $publication->setStatut(in_array($statut, [Publication::STATUT_BROUILLON, Publication::STATUT_PUBLIE], true) ? $statut : Publication::STATUT_BROUILLON);

            $violations = $validator->validate($publication);
            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $this->addFlash('error', $violation->getMessage());
                }

                return $this->render('publication/form.html.twig', [
                    'publication' => $publication,
                ]);
            }

            $em->flush();

            $this->addFlash('success', 'Publication modifiee avec succes !');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        return $this->render('publication/form.html.twig', [
            'publication' => $publication,
        ]);
    }

    #[Route('/delete/{id}', name: 'publication_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, PublicationRepository $repository, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Seuls les coachs et administrateurs peuvent supprimer une publication.');
        }

        $publication = $repository->find($id);

        if (!$publication) {
            throw $this->createNotFoundException('Publication non trouvee');
        }

        if (!$this->isCsrfTokenValid('delete_' . $publication->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('publication_show', ['id' => $publication->getId()]);
        }

        $em->remove($publication);
        $em->flush();

        $this->addFlash('success', 'Publication supprimee avec succes !');
        return $this->redirectToRoute('publication_index');
    }

    #[Route('/like/{id}', name: 'publication_like', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function like(int $id, Request $request, PublicationRepository $repository, EntityManagerInterface $em): Response
    {
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
