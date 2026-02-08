<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ligneCommandes')]
    #[ORM\JoinColumn(nullable: false)]
    private Commande $commande;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Produit $produit;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La quantité ne peut pas être vide')]
    #[Assert\Positive(message: 'La quantité doit être positive')]
    private int $quantite;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix unitaire ne peut pas être vide')]
    #[Assert\Positive(message: 'Le prix unitaire doit être positif')]
    private string $prixUnitaire;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le sous-total ne peut pas être vide')]
    #[Assert\Positive(message: 'Le sous-total doit être positif')]
    private string $sousTotal;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La remise doit être positive ou zéro', allowNull: true)]
    private ?string $remise = null;

    // GETTERS / SETTERS

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function setCommande(Commande $commande): self
    {
        $this->commande = $commande;
        return $this;
    }

    public function getProduit(): Produit
    {
        return $this->produit;
    }

    public function setProduit(Produit $produit): self
    {
        $this->produit = $produit;
        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): self
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    public function getSousTotal(): string
    {
        return $this->sousTotal;
    }

    public function setSousTotal(string $sousTotal): self
    {
        $this->sousTotal = $sousTotal;
        return $this;
    }

    public function getRemise(): ?string
    {
        return $this->remise;
    }

    public function setRemise(?string $remise): self
    {
        $this->remise = $remise;
        return $this;
    }
}
