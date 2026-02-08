<?php

namespace App\Tests\Unit\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Service\CommandeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CommandeServiceTest extends TestCase
{
    private $commandeRepository;
    private $entityManager;
    private $commandeService;

    protected function setUp(): void
    {
        $this->commandeRepository = $this->createMock(CommandeRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->commandeService = new CommandeService(
            $this->commandeRepository,
            $this->entityManager
        );
    }

    public function testCreateCommande(): void
    {
        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $user = new User();
        $user->setEmail('test@example.com');

        $produit = new Produit();
        $produit->setId(1);
        $produit->setNom('Vitamines');
        $produit->setPrix(19.99);

        $items = [
            [
                'produit' => $produit,
                'quantity' => 2,
            ]
        ];

        $commande = $this->commandeService->createCommande($user, $items);

        $this->assertInstanceOf(Commande::class, $commande);
        $this->assertEquals($user, $commande->getUtilisateur());
        $this->assertEquals('en_attente', $commande->getStatut());
        $this->assertNotEmpty($commande->getNumeroCommande());
    }

    public function testCalculateTotal(): void
    {
        $commande = new Commande();

        $ligne1 = new LigneCommande();
        $ligne1->setSousTotal(50);

        $ligne2 = new LigneCommande();
        $ligne2->setSousTotal(30);

        $commande->addLigneCommande($ligne1);
        $commande->addLigneCommande($ligne2);

        $total = $this->commandeService->calculateTotal($commande);

        $this->assertEquals(80, $total);
    }

    public function testUpdateStatus(): void
    {
        $commande = new Commande();
        $commande->setStatut('en_attente');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->commandeService->updateStatus($commande, 'validée');

        $this->assertEquals('validée', $result->getStatut());
    }

    public function testUpdateStatusInvalid(): void
    {
        $commande = new Commande();
        $commande->setStatut('en_attente');

        $result = $this->commandeService->updateStatus($commande, 'invalid_status');

        $this->assertEquals('en_attente', $result->getStatut());
    }

    public function testCancelCommande(): void
    {
        $commande = new Commande();
        $commande->setStatut('en_attente');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->commandeService->cancelCommande($commande);

        $this->assertEquals('annulée', $result->getStatut());
    }
}
