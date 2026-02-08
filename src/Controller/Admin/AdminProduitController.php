<?php

namespace App\Controller\Admin;

use App\Entity\Produit;
use App\Form\ProduitFormType;
use App\Repository\ProduitRepository;
use App\Service\ProduitService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products', name: 'admin_produit_')]
#[IsGranted('ROLE_ADMIN')]
class AdminProduitController extends AbstractController
{
    private $produitRepository;
    private $produitService;

    public function __construct(ProduitRepository $produitRepository, ProduitService $produitService)
    {
        $this->produitRepository = $produitRepository;
        $this->produitService = $produitService;
    }

    /**
     * Lister tous les produits
     */
    #[Route('/', name: 'list')]
    public function index(): Response
    {
        $produits = $this->produitRepository->findAll();

        return $this->render('admin/produit/list.html.twig', [
            'produits' => $produits,
        ]);
    }

    /**
     * Créer un nouveau produit
     */
    #[Route('/create', name: 'create')]
    public function create(Request $request): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitFormType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->produitService->createProduit(
                $form->getData()->getNom(),
                $form->getData()->getDescription(),
                $form->getData()->getPrix(),
                $form->getData()->getStock(),
                $form->getData()->getCategorie()
            );

            $this->addFlash('success', 'Produit créé avec succès');

            return $this->redirectToRoute('admin_produit_list');
        }

        return $this->render('admin/produit/create.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Éditer un produit
     */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $produit = $this->produitRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $form = $this->createForm(ProduitFormType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->produitRepository->save($produit, true);

            $this->addFlash('success', 'Produit modifié avec succès');

            return $this->redirectToRoute('admin_produit_list');
        }

        return $this->render('admin/produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    /**
     * Supprimer un produit
     */
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $produit = $this->produitRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $this->produitRepository->remove($produit, true);
            $this->addFlash('success', 'Produit supprimé avec succès');
        }

        return $this->redirectToRoute('admin_produit_list');
    }

    /**
     * Voir les produits avec stock faible
     */
    #[Route('/low-stock', name: 'low_stock')]
    public function lowStock(): Response
    {
        $produits = $this->produitRepository->findLowStock(10);

        return $this->render('admin/produit/low-stock.html.twig', [
            'produits' => $produits,
        ]);
    }
}
