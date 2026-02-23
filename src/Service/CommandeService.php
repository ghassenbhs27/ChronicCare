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
        private readonly CommandeEventStore $eventStore,
        private readonly GeoLocationService $geoLocationService
    ) {
    }

    public function createCommande(User $user, array $items, ?string $ipAddress = null, string $methodePaiement = 'card'): ?Commande
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
        
        // 🌍 Géolocalisation automatique
        if ($ipAddress) {
            $commande->setIpAddress($ipAddress);
            $locationData = $this->geoLocationService->getLocationByIp($ipAddress);
            if ($locationData) {
                $commande->setCountry($locationData['country']);
                $commande->setCity($locationData['city']);
                $commande->setRegion($locationData['region']);
                $commande->setLatitude($locationData['latitude']);
                $commande->setLongitude($locationData['longitude']);
                $commande->setTimezone($locationData['timezone']);
                $commande->setIsp($locationData['isp']);
            }
        }

        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        // Event Sourcing : Enregistrer la création
        $this->eventStore->recordCommandeCreated($commande, $user);

        return $commande;
    }

    private function generateOrderNumber(): string
    {
        return 'CMD-' . (new \DateTime())->format('YmdHis') . '-' . random_int(1000, 9999);
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
     * DEPRECATED : Utiliser applyTransition() avec le Workflow à la place
     */
    public function updateStatus(Commande $commande, string $newStatus): Commande
    {
        $validStatuts = ['en_attente', 'validee', 'expediee', 'livree', 'annulee'];
        if (in_array($newStatus, $validStatuts)) {
            $oldStatus = $commande->getStatut();
            $commande->setStatut($newStatus);
            $this->entityManager->flush();
            
            $this->eventStore->recordStatusChanged($commande, $oldStatus, $newStatus);
        }
        return $commande;
    }

    /**
     * Applique une transition du workflow
     */
    public function applyTransition(Commande $commande, string $transitionName): bool
    {
        if (!$this->commandeWorkflow->can($commande, $transitionName)) {
            return false;
        }
        $this->commandeWorkflow->apply($commande, $transitionName);
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
        return $this->updateStatus($commande, 'annulee');
    }

    public function getCommandesByStatus(string $status): array
    {
        return $this->commandeRepository->findByStatut($status);
    }
}
