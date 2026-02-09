<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Service\CartService;
use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart', name: 'cart_')]
class CartController extends AbstractController
{
    private $cartService;
    private $produitRepository;

    public function __construct(CartService $cartService, ProduitRepository $produitRepository)
    {
        $this->cartService = $cartService;
        $this->produitRepository = $produitRepository;
    }

    /**
     * Afficher le panier
     */
    #[Route('/', name: 'show')]
    public function show(): Response
    {
        $cart = $this->cartService->getCart();
        $total = $this->cartService->getCartTotal();
        $count = $this->cartService->getCartCount();

        return $this->render('cart/show.html.twig', [
            'cart' => $cart,
            'total' => $total,
            'count' => $count,
        ]);
    }

    /**
     * Ajouter un produit au panier
     */
    #[Route('/add/{id}', name: 'add', requirements: ['id' => '\d+'])]
    public function add(int $id, Request $request): Response
    {
        $produit = $this->produitRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $quantity = $request->query->getInt('quantity', 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        $this->cartService->addToCart($produit, $quantity);

        $this->addFlash('success', 'Produit ajouté au panier');

        return $this->redirectToRoute('produit_list');
    }

    /**
     * Retirer un produit du panier
     */
    #[Route('/remove/{id}', name: 'remove', requirements: ['id' => '\d+'])]
    public function remove(int $id): Response
    {
        $this->cartService->removeFromCart($id);

        $this->addFlash('success', 'Produit retiré du panier');

        return $this->redirectToRoute('cart_show');
    }

    /**
     * Mettre à jour la quantité
     */
    #[Route('/update/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $quantity = $request->request->getInt('quantity', 1);

        if ($quantity < 1) {
            $this->cartService->removeFromCart($id);
        } else {
            $this->cartService->updateQuantity($id, $quantity);
        }

        $this->addFlash('success', 'Panier mis à jour');

        return $this->redirectToRoute('cart_show');
    }

    /**
     * Vider le panier
     */
    #[Route('/clear', name: 'clear')]
    public function clear(): Response
    {
        $this->cartService->clearCart();

        $this->addFlash('success', 'Panier vidé');

        return $this->redirectToRoute('cart_show');
    }
}
