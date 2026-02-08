<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products', name: 'produit_')]
class ProduitController extends AbstractController
{
    private $produitRepository;

    public function __construct(ProduitRepository $produitRepository)
    {
        $this->produitRepository = $produitRepository;
    }

    /**
     * Afficher le catalogue des produits
     */
    #[Route('/', name: 'list')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 12;

        $produits = $this->produitRepository->findAllActive();
        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Afficher les détails d'un produit
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $produit = $this->produitRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    /**
     * Rechercher des produits
     */
    #[Route('/search', name: 'search')]
    public function search(Request $request): Response
    {
        $keyword = $request->query->get('keyword', '');
        $categorie = $request->query->get('categorie', '');
        $priceMin = $request->query->get('priceMin', 0);
        $priceMax = $request->query->get('priceMax', 999999);

        $results = [];

        if ($keyword) {
            $results = $this->produitRepository->searchByKeyword($keyword);
        } elseif ($categorie) {
            $results = $this->produitRepository->findByCategorie($categorie);
        } elseif ($priceMin || $priceMax) {
            $results = $this->produitRepository->findByPriceRange((float) $priceMin, (float) $priceMax);
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
