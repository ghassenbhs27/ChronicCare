<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'home')]
    #[Route('/', name: 'index')]
    #[Route('/contenu-educatif', name: 'contenu-educatif')]
    #[Route('/forum', name: 'forum')]
    public function index(): Response
    {
        return $this->render('front/index.html.twig');
    }

    #[Route('/coachs', name: 'coachs')]
    #[Route('/appointment', name: 'appointment')]
    #[Route('/services', name: 'services')]
    #[Route('/rendez-vous', name: 'rendez-vous')]
    public function coachs(): Response
    {
        return $this->render('front/coachs.html.twig');
    }

    #[Route('/connexion', name: 'connexion')]
    public function connexion(): Response
    {
        return $this->render('front/connexion.html.twig');
    }

    #[Route('/inscription', name: 'inscription')]
    #[Route('/terms', name: 'terms')]
    public function inscription(): Response
    {
        return $this->render('front/inscription.html.twig');
    }

    #[Route('/boutique', name: 'boutique')]
    public function boutique(Request $request, ProduitRepository $produitRepository): Response
    {
        $keyword = $request->query->get('keyword', '');
        $priceMin = $request->query->get('priceMin', 0);
        $priceMax = $request->query->get('priceMax', 999999);

        if ($keyword) {
            $produits = $produitRepository->searchByKeyword($keyword);
        } elseif ($priceMin || $priceMax) {
            $produits = $produitRepository->findByPriceRange((float)$priceMin, (float)$priceMax);
        } else {
            $produits = $produitRepository->findAllActive();
        }

        $categories = $produitRepository->findAllCategories();

        return $this->render('front/boutique.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'keyword' => $keyword,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
        ]);
    }

    #[Route('/service-details', name: 'service-details')]
    public function serviceDetails(): Response
    {
        return $this->render('front/boutique.html.twig');
    }

    #[Route('/faq', name: 'faq')]
    public function faq(): Response
    {
        return $this->render('front/faq.html.twig');
    }

    #[Route('/nutritionnistes', name: 'nutritionnistes')]
    public function nutritionnistes(): Response
    {
        return $this->render('front/nutritionnistes.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('/privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('front/privacy.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}
