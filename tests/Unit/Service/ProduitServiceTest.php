<?php

namespace App\Tests\Unit\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use App\Service\ProduitService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProduitServiceTest extends TestCase
{
    private $produitRepository;
    private $entityManager;
    private $produitService;

    protected function setUp(): void
    {
        $this->produitRepository = $this->createMock(ProduitRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->produitService = new ProduitService(
            $this->produitRepository,
            $this->entityManager
        );
    }

    public function testCreateProduit(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $produit = $this->produitService->createProduit(
            'Vitamines',
            'Complexe vitamines',
            19.99,
            100,
            'Vitamines'
        );

        $this->assertInstanceOf(Produit::class, $produit);
        $this->assertEquals('Vitamines', $produit->getNom());
        $this->assertEquals(19.99, $produit->getPrix());
        $this->assertEquals(100, $produit->getStock());
        $this->assertTrue($produit->isActive());
    }

    public function testDecreaseStock(): void
    {
        $produit = new Produit();
        $produit->setStock(100);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->produitService->decreaseStock($produit, 50);

        $this->assertTrue($result);
        $this->assertEquals(50, $produit->getStock());
    }

    public function testDecreaseStockInsufficient(): void
    {
        $produit = new Produit();
        $produit->setStock(10);

        $result = $this->produitService->decreaseStock($produit, 50);

        $this->assertFalse($result);
        $this->assertEquals(10, $produit->getStock());
    }

    public function testCheckAvailability(): void
    {
        $produit = new Produit();
        $produit->setStock(100);
        $produit->setIsActive(true);

        $this->assertTrue($this->produitService->checkAvailability($produit, 50));
        $this->assertFalse($this->produitService->checkAvailability($produit, 150));
    }

    public function testUpdateStock(): void
    {
        $produit = new Produit();
        $produit->setStock(100);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->produitService->updateStock($produit, 200);

        $this->assertEquals(200, $result->getStock());
    }
}
