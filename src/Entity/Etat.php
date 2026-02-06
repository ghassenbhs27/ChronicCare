<?php

namespace App\Entity;

use App\Repository\EtatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EtatRepository::class)]
class Etat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // =========================
    // RELATION AVEC USER
    // =========================

    #[ORM\ManyToOne(inversedBy: 'etats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $utilisateur = null;

    // =========================
    // DONNÉES DE SUIVI
    // =========================

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column]
    private float $valeur;

    #[ORM\Column(length: 50)]
    private string $unite;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateReleve;

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getValeur(): float
    {
        return $this->valeur;
    }

    public function setValeur(float $valeur): self
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): self
    {
        $this->unite = $unite;
        return $this;
    }

    public function getDateReleve(): \DateTimeInterface
    {
        return $this->dateReleve;
    }

    public function setDateReleve(\DateTimeInterface $dateReleve): self
    {
        $this->dateReleve = $dateReleve;
        return $this;
    }
}
