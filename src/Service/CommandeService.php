<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommandeService
{
    private $commandeRepository;
    private $entityManager;

    public function __construct(CommandeRepository $commandeRepository, EntityManagerInterface $entityManager)
    {
        $this->commandeRepository = $commandeRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Créer une nouvelle commande
     */
    public function createCommande(User $user, array $items): Commande
    {
        $commande = new Commande();
        $commande->setUtilisateur($user);
        $commande->setNumeroCommande($this->generateOrderNumber());
        $commande->setStatut('en_attente');

        $total = 0;

        // Ajouter les lignes de commande
        foreach ($items as $item) {
            $ligneCommande = new LigneCommande();
            $ligneCommande->setProduit($item['produit']);
            $ligneCommande->setQuantite($item['quantity']);
            $ligneCommande->setPrixUnitaire($item['produit']->getPrix());
            
            $sousTotal = (float) $item['produit']->getPrix() * $item['quantity'];
            $ligneCommande->setSousTotal($sousTotal);
            $ligneCommande->setRemise(0);

            $commande->addLigneCommande($ligneCommande);
            $total += $sousTotal;
        }

        $commande->setTotal($total);

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        return $commande;
    }

    /**
     * Générer un numéro de commande unique
     */
    private function generateOrderNumber(): string
    {
        $date = new \DateTime();
        $random = random_int(1000, 9999);
        return 'CMD-' . $date->format('YmdHis') . '-' . $random;
    }

    /**
     * Calculer le total d'une commande
     */
    public function calculateTotal(Commande $commande): float
    {
        $total = 0;

        foreach ($commande->getLigneCommandes() as $ligne) {
            $total += (float) $ligne->getSousTotal();
        }

        return round($total, 2);
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateStatus(Commande $commande, string $newStatus): Commande
    {
        $validStatuts = ['en_attente', 'validée', 'expédiée', 'livrée', 'annulée'];

        if (in_array($newStatus, $validStatuts)) {
            $commande->setStatut($newStatus);
            $this->entityManager->flush();
        }

        return $commande;
    }

    /**
     * Obtenir toutes les commandes d'un utilisateur
     */
    public function getUserCommandes(User $user): array
    {
        return $this->commandeRepository->findByUtilisateur($user);
    }

    /**
     * Récupérer une commande par ID
     */
    public function getCommandeById(int $id): ?Commande
    {
        return $this->commandeRepository->find($id);
    }

    /**
     * Récupérer une commande par numéro
     */
    public function getCommandeByNumero(string $numero): ?Commande
    {
        return $this->commandeRepository->findByNumeroCommande($numero);
    }

    /**
     * Annuler une commande
     */
    public function cancelCommande(Commande $commande): Commande
    {
        return $this->updateStatus($commande, 'annulée');
    }

    /**
     * Obtenir les commandes d'un statut donné
     */
    public function getCommandesByStatus(string $status): array
    {
        return $this->commandeRepository->findByStatut($status);
    }
}
