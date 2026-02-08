<?php

namespace App\Service;

use App\Entity\Produit;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private $requestStack;
    private const CART_SESSION_KEY = 'cart';

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Initier le panier si vide
     */
    private function initCart(): void
    {
        $session = $this->requestStack->getSession();
        if (!$session->has(self::CART_SESSION_KEY)) {
            $session->set(self::CART_SESSION_KEY, []);
        }
    }

    /**
     * Ajouter un produit au panier
     */
    public function addToCart(Produit $produit, int $quantity = 1): void
    {
        $this->initCart();
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY);

        $productId = $produit->getId();

        // Si le produit existe déjà, augmenter la quantité
        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            // Ajouter le produit au panier
            $cart[$productId] = [
                'id' => $productId,
                'nom' => $produit->getNom(),
                'prix' => $produit->getPrix(),
                'quantity' => $quantity,
            ];
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Retirer un produit du panier
     */
    public function removeFromCart(int $productId): void
    {
        $this->initCart();
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY);

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Mettre à jour la quantité d'un produit
     */
    public function updateQuantity(int $productId, int $quantity): void
    {
        $this->initCart();
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY);

        if (isset($cart[$productId])) {
            if ($quantity <= 0) {
                $this->removeFromCart($productId);
            } else {
                $cart[$productId]['quantity'] = $quantity;
            }
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

    /**
     * Vider le panier
     */
    public function clearCart(): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::CART_SESSION_KEY, []);
    }

    /**
     * Obtenir le contenu du panier
     */
    public function getCart(): array
    {
        $this->initCart();
        $session = $this->requestStack->getSession();
        return $session->get(self::CART_SESSION_KEY, []);
    }

    /**
     * Obtenir le nombre d'articles dans le panier
     */
    public function getCartCount(): int
    {
        $cart = $this->getCart();
        $count = 0;

        foreach ($cart as $item) {
            $count += $item['quantity'];
        }

        return $count;
    }

    /**
     * Calculer le total du panier
     */
    public function getCartTotal(): float
    {
        $cart = $this->getCart();
        $total = 0;

        foreach ($cart as $item) {
            $total += (float) $item['prix'] * $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * Vérifier si le panier est vide
     */
    public function isEmpty(): bool
    {
        $cart = $this->getCart();
        return count($cart) === 0;
    }
}
