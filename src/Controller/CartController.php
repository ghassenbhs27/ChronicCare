<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart', name: 'cart_')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ProduitRepository $produitRepository
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): Response
    {
        if ($redirect = $this->denyIfProfessionalOrAdmin()) {
            return $redirect;
        }

        return $this->render('cart/show.html.twig', [
            'cart' => $this->cartService->getCart(),
            'total' => $this->cartService->getCartTotal(),
            'count' => $this->cartService->getCartCount(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]
    #[Route('/add/{id}', name: 'add_with_id', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function add(?int $id, Request $request): Response
    {
        if ($redirect = $this->denyIfProfessionalOrAdmin($request)) {
            return $redirect;
        }

        $productId = $id ?? $request->query->getInt('id');
        if (!$productId) {
            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['success' => false, 'message' => 'ID produit manquant']);
            }
            throw $this->createNotFoundException('Produit non trouve');
        }

        $produit = $this->produitRepository->find($productId);
        if (!$produit) {
            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['success' => false, 'message' => 'Produit non trouve']);
            }
            throw $this->createNotFoundException('Produit non trouve');
        }

        $quantity = max(1, $request->query->getInt('quantity', 1));
        $this->cartService->addToCart($produit, $quantity);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            $imageName = strtolower(str_replace([' ', 'é', 'è', 'ê', 'à'], ['-', 'e', 'e', 'e', 'a'], $produit->getNom())) . '.jpg';
            return new JsonResponse([
                'success' => true,
                'message' => 'Produit ajoute au panier',
                'cartCount' => $this->cartService->getCartCount(),
                'cartTotal' => $this->cartService->getCartTotal(),
                'price' => $produit->getPrix(),
                'name' => $produit->getNom(),
                'image' => '/assets/img/products/' . $imageName,
                'details' => 'Categorie : ' . $produit->getCategorie(),
                'cartMessage' => $this->cartService->getCartCount() . ' article(s) dans votre panier.',
            ]);
        }

        $this->addFlash('success', 'Produit ajoute au panier');

        return $this->redirect($request->headers->get('Referer', $this->generateUrl('boutique')));
    }

    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function remove(int $id): Response
    {
        if ($redirect = $this->denyIfProfessionalOrAdmin()) {
            return $redirect;
        }

        $this->cartService->removeFromCart($id);
        $this->addFlash('success', 'Produit retire du panier');

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/update/{id}', name: 'update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        if ($redirect = $this->denyIfProfessionalOrAdmin()) {
            return $redirect;
        }

        $quantity = $request->request->getInt('quantity', 1);
        if ($quantity < 1) {
            $this->cartService->removeFromCart($id);
        } else {
            $this->cartService->updateQuantity($id, $quantity);
        }
        $this->addFlash('success', 'Panier mis a jour');

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/clear', name: 'clear', methods: ['GET'])]
    public function clear(): Response
    {
        if ($redirect = $this->denyIfProfessionalOrAdmin()) {
            return $redirect;
        }

        $this->cartService->clearCart();
        $this->addFlash('success', 'Panier vide');

        return $this->redirectToRoute('cart_show');
    }

    private function denyIfProfessionalOrAdmin(?Request $request = null): ?Response
    {
        if (!$this->getUser()) {
            return null;
        }

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COACH') || $this->isGranted('ROLE_NUTRITIONNISTE')) {
            if ($request && $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Le panier et les commandes sont reserves aux patients.',
                ], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('warning', 'Le panier et les commandes sont reserves aux patients.');
            return $this->redirectToRoute('boutique');
        }

        return null;
    }
}
