<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Form\ProduitFormType;
use App\Repository\ProduitRepository;
use App\Service\ProduitService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products', name: 'admin_produit_')]
#[IsGranted('ROLE_ADMIN')]
class AdminProduitController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->produitRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC');

        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10 // 10 produits par page
        );

        return $this->render('admin/produit/list.html.twig', [
            'produits' => $pagination,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitFormType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Produit créé avec succès');
            return $this->redirectToRoute('admin_produit_list');
        }

        return $this->render('admin/produit/create.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $form = $this->createForm(ProduitFormType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Produit modifié avec succès');
            return $this->redirectToRoute('admin_produit_list');
        }

        return $this->render('admin/produit/edit.html.twig', ['produit' => $produit, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $this->entityManager->remove($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Produit supprimé avec succès');
        }

        return $this->redirectToRoute('admin_produit_list');
    }

    #[Route('/low-stock', name: 'low_stock', methods: ['GET'])]
    public function lowStock(): Response
    {
        return $this->render('admin/produit/low-stock.html.twig', [
            'produits' => $this->produitRepository->findLowStock(10),
        ]);
    }
}
