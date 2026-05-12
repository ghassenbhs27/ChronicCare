<?php

namespace App\Tests\Unit\Service;

use App\Entity\Panier;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\PanierRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class CartServiceTest extends TestCase
{
    private CartService $cartService;
    /** @var PanierRepository&MockObject */
    private PanierRepository $panierRepository;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;
    /** @var Security&MockObject */
    private Security $security;
    private User $user;
    /** @var array<int, Panier> */
    private array $storage = [];

    protected function setUp(): void
    {
        $this->user = $this->createMock(User::class);

        $this->security = $this->createMock(Security::class);
        $this->security->method('getUser')->willReturn($this->user);

        $this->panierRepository = $this->createMock(PanierRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->panierRepository->method('findOneBy')->willReturnCallback(function (array $criteria): ?Panier {
            $productId = $this->resolveProductId($criteria['produit'] ?? null);

            if (!$productId || !isset($this->storage[$productId])) {
                return null;
            }

            return $this->storage[$productId];
        });

        $this->panierRepository->method('findBy')->willReturnCallback(function (array $criteria): array {
            if (array_key_exists('produit', $criteria)) {
                $productId = $this->resolveProductId($criteria['produit']);

                if (!$productId || !isset($this->storage[$productId])) {
                    return [];
                }

                return [$this->storage[$productId]];
            }

            return array_values($this->storage);
        });

        $this->entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            if (!$entity instanceof Panier) {
                return;
            }

            $productId = $entity->getProduit()->getId();
            if ($productId) {
                $this->storage[$productId] = $entity;
            }
        });

        $this->entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            if (!$entity instanceof Panier) {
                return;
            }

            $productId = $entity->getProduit()->getId();
            if ($productId) {
                unset($this->storage[$productId]);
            }
        });

        $this->cartService = new CartService($this->panierRepository, $this->entityManager, $this->security);
    }

    private function createProduit(int $id, string $nom, string $prix, string $categorie = 'Sante'): Produit
    {
        $produit = $this->createMock(Produit::class);
        $produit->method('getId')->willReturn($id);
        $produit->method('getNom')->willReturn($nom);
        $produit->method('getPrix')->willReturn($prix);
        $produit->method('getCategorie')->willReturn($categorie);

        return $produit;
    }

    private function resolveProductId(mixed $value): ?int
    {
        if ($value instanceof Produit) {
            return $value->getId();
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    public function testAddToCart(): void
    {
        $produit = $this->createProduit(1, 'Vitamines', '19.99');

        $this->cartService->addToCart($produit, 2);

        $cart = $this->cartService->getCart();

        $this->assertCount(1, $cart);
        $this->assertEquals(2, $cart[1]['quantity']);
        $this->assertEquals('Vitamines', $cart[1]['nom']);
    }

    public function testAddToCartIncrementQuantity(): void
    {
        $produit = $this->createProduit(1, 'Test', '10.00');

        $this->cartService->addToCart($produit, 1);
        $this->cartService->addToCart($produit, 2);

        $cart = $this->cartService->getCart();

        $this->assertEquals(3, $cart[1]['quantity']);
    }

    public function testRemoveFromCart(): void
    {
        $produit = $this->createProduit(1, 'Test', '10.00');

        $this->cartService->addToCart($produit, 1);
        $this->cartService->removeFromCart(1);

        $cart = $this->cartService->getCart();

        $this->assertCount(0, $cart);
    }

    public function testUpdateQuantity(): void
    {
        $produit = $this->createProduit(1, 'Test', '10.00');

        $this->cartService->addToCart($produit, 1);
        $this->cartService->updateQuantity(1, 5);

        $cart = $this->cartService->getCart();

        $this->assertEquals(5, $cart[1]['quantity']);
    }

    public function testClearCart(): void
    {
        $produit = $this->createProduit(1, 'Test', '10.00');

        $this->cartService->addToCart($produit, 1);
        $this->cartService->clearCart();

        $cart = $this->cartService->getCart();

        $this->assertCount(0, $cart);
    }

    public function testGetCartTotal(): void
    {
        $produit1 = $this->createProduit(1, 'P1', '10.00');
        $produit2 = $this->createProduit(2, 'P2', '20.00');

        $this->cartService->addToCart($produit1, 2);
        $this->cartService->addToCart($produit2, 1);

        $total = $this->cartService->getCartTotal();

        $this->assertEquals(40, $total);
    }

    public function testGetCartCount(): void
    {
        $produit1 = $this->createProduit(1, 'P1', '10.00');

        $this->cartService->addToCart($produit1, 5);

        $count = $this->cartService->getCartCount();

        $this->assertEquals(5, $count);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->cartService->isEmpty());

        $produit = $this->createProduit(1, 'Test', '10.00');

        $this->cartService->addToCart($produit, 1);

        $this->assertFalse($this->cartService->isEmpty());
    }

    public function testAnonymousUserGetsEmptyCart(): void
    {
        /** @var Security&MockObject $anonymousSecurity */
        $anonymousSecurity = $this->createMock(Security::class);
        $anonymousSecurity->method('getUser')->willReturn(null);
        $anonymousCartService = new CartService($this->panierRepository, $this->entityManager, $anonymousSecurity);

        $produit = $this->createProduit(1, 'Test', '10.00');
        $anonymousCartService->addToCart($produit, 2);

        $this->assertSame([], $anonymousCartService->getCart());
        $this->assertTrue($anonymousCartService->isEmpty());
    }
}
