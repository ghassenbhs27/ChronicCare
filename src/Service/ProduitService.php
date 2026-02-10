<?php

namespace App\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProduitService
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createProduit(string $nom, string $description, $prix, int $stock, string $categorie): Produit
    {
        $produit = new Produit();
        $produit->setNom($nom);
        $produit->setDescription($description);
        $produit->setPrix((string) $prix);
        $produit->setStock($stock);
        $produit->setCategorie($categorie);
        $produit->setIsActive(true);

        $this->entityManager->persist($produit);
        $this->entityManager->flush();

        return $produit;
    }

    public function updateStock(Produit $produit, int $newStock): Produit
    {
        $produit->setStock($newStock);
        $this->entityManager->flush();
        return $produit;
    }

    public function decreaseStock(Produit $produit, int $quantity): bool
    {
        if ($produit->getStock() < $quantity) {
            return false;
        }
        $produit->setStock($produit->getStock() - $quantity);
        $this->entityManager->flush();
        return true;
    }

    public function countLowStock(int $threshold = 10): int
    {
        return count($this->produitRepository->findLowStock($threshold));
    }
}
