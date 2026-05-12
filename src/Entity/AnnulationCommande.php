<?php

namespace App\Entity;

use App\Repository\AnnulationCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnnulationCommandeRepository::class)]
#[ORM\Table(name: 'annulation_commande')]
class AnnulationCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $raison = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statutAvantAnnulation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $montantRembourse = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $stockRestitue = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateAnnulation;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motifAnnulation = null;

    public function __construct()
    {
        $this->dateAnnulation = new \DateTimeImmutable();
    }

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

    public function getRaison(): ?string
    {
        return $this->raison;
    }

    public function setRaison(?string $raison): self
    {
        $this->raison = $raison;
        return $this;
    }

    public function getUtilisateur(): User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getStatutAvantAnnulation(): ?string
    {
        return $this->statutAvantAnnulation;
    }

    public function setStatutAvantAnnulation(?string $statutAvantAnnulation): self
    {
        $this->statutAvantAnnulation = $statutAvantAnnulation;
        return $this;
    }

    public function getMontantRembourse(): ?string
    {
        return $this->montantRembourse;
    }

    public function setMontantRembourse(?string $montantRembourse): self
    {
        $this->montantRembourse = $montantRembourse;
        return $this;
    }

    public function isStockRestitue(): bool
    {
        return $this->stockRestitue;
    }

    public function setStockRestitue(bool $stockRestitue): self
    {
        $this->stockRestitue = $stockRestitue;
        return $this;
    }

    public function getDateAnnulation(): \DateTimeImmutable
    {
        return $this->dateAnnulation;
    }

    public function setDateAnnulation(\DateTimeImmutable $dateAnnulation): self
    {
        $this->dateAnnulation = $dateAnnulation;
        return $this;
    }

    public function getMotifAnnulation(): ?string
    {
        return $this->motifAnnulation;
    }

    public function setMotifAnnulation(?string $motifAnnulation): self
    {
        $this->motifAnnulation = $motifAnnulation;
        return $this;
    }
}
