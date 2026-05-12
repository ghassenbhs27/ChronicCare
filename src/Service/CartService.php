<?php

namespace App\Service;

use App\Entity\Panier;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\PanierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CartService
{
    public function __construct(
        private readonly PanierRepository $panierRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security
    ) {
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    private function buildImagePath(string $productName): string
    {
        $imageName = strtolower(str_replace([' ', 'é', 'è', 'ê', 'à'], ['-', 'e', 'e', 'e', 'a'], $productName)) . '.jpg';

        return '/assets/img/products/' . $imageName;
    }

    public function addToCart(Produit $produit, int $quantity = 1): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return;
        }

        $productId = $produit->getId();
        if (!$productId) {
            return;
        }

        $quantity = max(1, $quantity);
        $panierItem = $this->panierRepository->findOneBy([
            'utilisateur' => $user,
            'produit' => $produit,
        ]);

        if ($panierItem) {
            $panierItem->setQuantite($panierItem->getQuantite() + $quantity);
        } else {
            $panierItem = new Panier();
            $panierItem->setUtilisateur($user);
            $panierItem->setProduit($produit);
            $panierItem->setQuantite($quantity);
            $panierItem->setPrixUnitaire($produit->getPrix());
            $this->entityManager->persist($panierItem);
        }

        $panierItem->setPrixUnitaire($produit->getPrix());
        $this->entityManager->flush();
    }

    public function removeFromCart(int $productId): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return;
        }

        $items = $this->panierRepository->findBy([
            'utilisateur' => $user,
            'produit' => $productId,
        ]);

        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $this->entityManager->remove($item);
        }

        $this->entityManager->flush();
    }

    public function updateQuantity(int $productId, int $quantity): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeFromCart($productId);

            return;
        }

        $panierItem = $this->panierRepository->findOneBy([
            'utilisateur' => $user,
            'produit' => $productId,
        ]);

        if (!$panierItem) {
            return;
        }

        $panierItem->setQuantite($quantity);
        $this->entityManager->flush();
    }

    public function clearCart(): void
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return;
        }

        $items = $this->panierRepository->findBy(['utilisateur' => $user]);
        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $this->entityManager->remove($item);
        }

        $this->entityManager->flush();
    }

    public function getCart(): array
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            return [];
        }

        $items = $this->panierRepository->findBy(['utilisateur' => $user]);
        $cart = [];

        foreach ($items as $item) {
            $produit = $item->getProduit();
            $productId = $produit->getId();
            if (!$productId) {
                continue;
            }

            $cart[$productId] = [
                'id' => $productId,
                'nom' => $produit->getNom(),
                'prix' => $item->getPrixUnitaire(),
                'quantity' => $item->getQuantite(),
                'categorie' => $produit->getCategorie(),
                'image' => $this->buildImagePath($produit->getNom()),
            ];
        }

        return $cart;
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
