<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class CommandeService
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly ProduitService $produitService,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $commandeWorkflow,
        private readonly CommandeEventStore $eventStore
    ) {
    }

    public function createCommande(User $user, array $items, ?string $adresse = null, string $methodePaiement = 'card'): ?Commande
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
        $commande->setMethodePaiement($methodePaiement);
        if ($adresse !== null) {
            $commande->setAdresse($adresse);
        }

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        // 🔥 Event Sourcing : Enregistrer la création
        $this->eventStore->recordCommandeCreated($commande, $user);

        return $commande;
    }

    private function generateOrderNumber(): string
    {
        return 'CMD-' . (new \DateTime())->format('YmdHis') . '-' . random_int(1000, 9999);
    }

    /**
     * ⚠️ DEPRECATED : Utiliser applyTransition() avec le Workflow à la place
     * Cette méthode reste pour la compatibilité mais n'est plus recommandée
     */
    public function updateStatus(Commande $commande, string $newStatus): Commande
    {
        $validStatuts = ['en_attente', 'validee', 'expediee', 'livree', 'annulee'];
        if (in_array($newStatus, $validStatuts)) {
            $oldStatus = $commande->getStatut();
            $commande->setStatut($newStatus);
            $this->entityManager->flush();
            
            // Enregistrer dans l'event store même en mode legacy
            $this->eventStore->recordStatusChanged($commande, $oldStatus, $newStatus);
        }
        return $commande;
    }

    /**
     * 🔥 NOUVELLE MÉTHODE : Applique une transition du workflow
     * Utilise le State Machine pour garantir les transitions valides
     */
    public function applyTransition(Commande $commande, string $transitionName): bool
    {
        // Vérifier que la transition est possible
        if (!$this->commandeWorkflow->can($commande, $transitionName)) {
            return false;
        }

        // Appliquer la transition (déclenche automatiquement les events)
        $this->commandeWorkflow->apply($commande, $transitionName);
        
        // Sauvegarder en base
        $this->entityManager->flush();

        return true;
    }

    /**
     * Récupère les transitions disponibles pour une commande
     */
    public function getAvailableTransitions(Commande $commande): array
    {
        return $this->commandeWorkflow->getEnabledTransitions($commande);
    }

    /**
     * Vérifie si une transition est possible
     */
    public function canApplyTransition(Commande $commande, string $transitionName): bool
    {
        return $this->commandeWorkflow->can($commande, $transitionName);
    }

    /**
     * Récupère l'historique complet d'une commande
     */
    public function getCommandeHistory(Commande $commande): array
    {
        return $this->eventStore->getCommandeHistory($commande);
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
