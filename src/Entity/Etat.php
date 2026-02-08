<?php

namespace App\Entity;

use App\Repository\EtatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;


#[ORM\Entity(repositoryClass: EtatRepository::class)]
class Etat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Length(min: 2, max: 80)]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La valeur est obligatoire.")]
    #[Assert\PositiveOrZero(message: "La valeur doit être >= 0.")]
    private ?float $valeur = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "L'unité est obligatoire.")]
    #[Assert\Length(min: 1, max: 30)]
    private ?string $unite = null;

    #[ORM\Column(type: "date_immutable")]
    #[Assert\NotNull(message: "La date relevé est obligatoire.")]
    #[Assert\LessThanOrEqual("today", message: "La date relevé ne peut pas être dans le futur.")]
    private ?\DateTimeImmutable $dateReleve = null;

    // Relation optionnelle : Etat -> Utilisateur
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $utilisateur = null;


    public function getId(): ?int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getValeur(): ?float { return $this->valeur; }
    public function setValeur(float $valeur): self { $this->valeur = $valeur; return $this; }

    public function getUnite(): ?string { return $this->unite; }
    public function setUnite(string $unite): self { $this->unite = $unite; return $this; }

    public function getDateReleve(): ?\DateTimeImmutable { return $this->dateReleve; }
    public function setDateReleve(?\DateTimeImmutable $dateReleve): self { $this->dateReleve = $dateReleve; return $this; }

    public function getUtilisateur(): ?User { return $this->utilisateur; }

    public function setUtilisateur(?User $utilisateur): self { $this->utilisateur = $utilisateur; return $this; }

}
