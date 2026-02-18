<?php

namespace App\Service;

use App\Entity\Produit;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    private function initCart(): void
    {
        $session = $this->requestStack->getSession();
        if (!$session->has(self::CART_SESSION_KEY)) {
            $session->set(self::CART_SESSION_KEY, []);
        }
    }

    public function addToCart(Produit $produit, int $quantity = 1): void
    {
        $this->initCart();
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY);

        $productId = $produit->getId();
        $imageName = strtolower(str_replace([' ', 'é', 'è', 'ê', 'à'], ['-', 'e', 'e', 'e', 'a'], $produit->getNom())) . '.jpg';

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += $quantity;
        } else {
            $cart[$productId] = [
                'id' => $productId,
                'nom' => $produit->getNom(),
                'prix' => $produit->getPrix(),
                'quantity' => $quantity,
                'categorie' => $produit->getCategorie(),
                'image' => '/assets/img/products/' . $imageName,
            ];
        }

        $session->set(self::CART_SESSION_KEY, $cart);
    }

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

    public function clearCart(): void
    {
        $this->requestStack->getSession()->set(self::CART_SESSION_KEY, []);
    }

    public function getCart(): array
    {
        $this->initCart();
        return $this->requestStack->getSession()->get(self::CART_SESSION_KEY, []);
    }

    public function getCartCount(): int
    {
        $cart = $this->getCart();
        $count = 0;
        foreach ($cart as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }

    public function getCartTotal(): float
    {
        $cart = $this->getCart();
        $total = 0;
        foreach ($cart as $item) {
            $total += (float) $item['prix'] * $item['quantity'];
        }
        return round($total, 2);
    }

    public function isEmpty(): bool
    {
        return count($this->getCart()) === 0;
    }
}
