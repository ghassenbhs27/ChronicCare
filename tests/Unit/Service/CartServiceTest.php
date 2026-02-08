<?php

namespace App\Tests\Unit\Service;

use App\Entity\Produit;
use App\Service\CartService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartServiceTest extends TestCase
{
    private $cartService;
    private $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->cartService = new CartService($this->session);
    }

    public function testAddToCart(): void
    {
        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Vitamines');
        $produit->setPrix(19.99);

        $this->cartService->addToCart($produit, 2);

        $cart = $this->cartService->getCart();

        $this->assertCount(1, $cart);
        $this->assertEquals(2, $cart[1]['quantity']);
        $this->assertEquals('Vitamines', $cart[1]['nom']);
    }

    public function testAddToCartIncrementQuantity(): void
    {
        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Test');
        $produit->setPrix(10);

        $this->cartService->addToCart($produit, 1);
        $this->cartService->addToCart($produit, 2);

        $cart = $this->cartService->getCart();

        $this->assertEquals(3, $cart[1]['quantity']);
    }

    public function testRemoveFromCart(): void
    {
        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Test');
        $produit->setPrix(10);

        $this->cartService->addToCart($produit, 1);
        $this->cartService->removeFromCart(1);

        $cart = $this->cartService->getCart();

        $this->assertCount(0, $cart);
    }

    public function testUpdateQuantity(): void
    {
        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Test');
        $produit->setPrix(10);

        $this->cartService->addToCart($produit, 1);
        $this->cartService->updateQuantity(1, 5);

        $cart = $this->cartService->getCart();

        $this->assertEquals(5, $cart[1]['quantity']);
    }

    public function testClearCart(): void
    {
        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Test');
        $produit->setPrix(10);

        $this->cartService->addToCart($produit, 1);
        $this->cartService->clearCart();

        $cart = $this->cartService->getCart();

        $this->assertCount(0, $cart);
    }

    public function testGetCartTotal(): void
    {
        $produit1 = new Produit();
        $produit1->setId(1);
        $produit1->setNom('P1');
        $produit1->setPrix(10);

        $produit2 = new Produit();
        $produit2->setId(2);
        $produit2->setNom('P2');
        $produit2->setPrix(20);

        $this->cartService->addToCart($produit1, 2);
        $this->cartService->addToCart($produit2, 1);

        $total = $this->cartService->getCartTotal();

        $this->assertEquals(40, $total);
    }

    public function testGetCartCount(): void
    {
        $produit1 = new Produit();
        $produit1->setId(1);
        $produit1->setNom('P1');
        $produit1->setPrix(10);

        $this->cartService->addToCart($produit1, 5);

        $count = $this->cartService->getCartCount();

        $this->assertEquals(5, $count);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->cartService->isEmpty());

        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Test');
        $produit->setPrix(10);

        $this->cartService->addToCart($produit, 1);

        $this->assertFalse($this->cartService->isEmpty());
    }
}
