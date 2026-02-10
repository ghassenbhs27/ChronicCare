<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommandeService
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly ProduitService $produitService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function createCommande(User $user, array $items, ?string $adresse = null): ?Commande
    {
        foreach ($items as $item) {
            $produit = $this->produitRepository->find($item['id']);
            if (!$produit || $produit->getStock() < $item['quantity']) {
                return null;
            }
        }

        $commande = new Commande();
        $commande->setUtilisateur($user);
        $commande->setNumeroCommande($this->generateOrderNumber());
        $commande->setStatut('en_attente');

        $total = 0;

        foreach ($items as $item) {
            $produit = $this->produitRepository->find($item['id']);
            if (!$produit) {
                continue;
            }

            $this->produitService->decreaseStock($produit, $item['quantity']);

            $ligneCommande = new LigneCommande();
            $ligneCommande->setProduit($produit);
            $ligneCommande->setQuantite($item['quantity']);
            $ligneCommande->setPrixUnitaire($produit->getPrix());

            $sousTotal = (float) $produit->getPrix() * $item['quantity'];
            $ligneCommande->setSousTotal((string) $sousTotal);
            $ligneCommande->setRemise(null);

            $commande->addLigneCommande($ligneCommande);
            $total += $sousTotal;
        }

        $commande->setTotal((string) round($total, 2));
        if ($adresse !== null) {
            $commande->setAdresse($adresse);
        }

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        return $commande;
    }

    private function generateOrderNumber(): string
    {
        return 'CMD-' . (new \DateTime())->format('YmdHis') . '-' . random_int(1000, 9999);
    }

    public function updateStatus(Commande $commande, string $newStatus): Commande
    {
        $validStatuts = ['en_attente', 'validée', 'expédiée', 'livrée', 'annulée'];
        if (in_array($newStatus, $validStatuts)) {
            $commande->setStatut($newStatus);
            $this->entityManager->flush();
        }
        return $commande;
    }

    public function getUserCommandes(User $user): array
    {
        return $this->commandeRepository->findByUtilisateur($user);
    }

    public function getCommandeById(int $id): ?Commande
    {
        return $this->commandeRepository->find($id);
    }

    public function getCommandesByStatus(string $status): array
    {
        return $this->commandeRepository->findByStatut($status);
    }
}
