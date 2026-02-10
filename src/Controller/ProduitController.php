<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', name: 'produit_')]
class ProduitController extends AbstractController
{
    public function __construct(private readonly ProduitRepository $produitRepository)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $produits = $this->produitRepository->findAllActive();
        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $produit = $this->produitRepository->findOneBy(['id' => $id, 'isActive' => true]);

        if (!$produit) {
            $this->addFlash('error', 'Produit non trouvé ou indisponible.');
            return $this->redirectToRoute('boutique');
        }

        return $this->render('produit/show.html.twig', ['produit' => $produit]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $keyword = $request->query->get('keyword', '');
        $categorie = $request->query->get('categorie', '');
        $priceMin = (float) $request->query->get('priceMin', 0);
        $priceMax = (float) $request->query->get('priceMax', 999999);

        $results = [];
        if ($keyword) {
            $results = $this->produitRepository->searchByKeyword($keyword);
        } elseif ($categorie) {
            $results = $this->produitRepository->findByCategorie($categorie);
        } elseif ($priceMin > 0 || $priceMax < 999999) {
            $results = $this->produitRepository->findByPriceRange($priceMin, $priceMax);
        }

        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/search.html.twig', [
            'results' => $results,
            'keyword' => $keyword,
            'categorie' => $categorie,
            'categories' => $categories,
        ]);
    }
}
