<?php

namespace App\Entity;

use App\Repository\ActiviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;


#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Length(min: 2, max: 80)]
    #[Assert\Regex(
        pattern: "/^(?!.*<[^>]+>)[\\p{L}\\p{N}\\s'’\\-]+$/u",
        message: "Le type ne doit pas contenir de HTML ou de code JavaScript."
    )]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La durée est obligatoire.")]
    #[Assert\Positive(message: "La durée doit être > 0.")]
    #[Assert\LessThanOrEqual(1440, message: "Durée invalide.")]
    private ?int $duree = null; // minutes

    #[ORM\Column]
    #[Assert\NotNull(message: "Les calories sont obligatoires.")]
    #[Assert\PositiveOrZero(message: "Calories doivent être >= 0.")]
    #[Assert\LessThanOrEqual(20000, message: "Calories invalides.")]
    private ?int $calories = null;

    #[ORM\Column(type: "date_immutable")]
    #[Assert\NotNull(message: "La date activité est obligatoire.")]
    #[Assert\LessThanOrEqual("today", message: "La date activité ne peut pas être dans le futur.")]
    private ?\DateTimeImmutable $dateActivite = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Assert\Length(max: 2000)]
    #[Assert\Regex(
        pattern: "/^(?!.*<[^>]+>)[^<>]*$/u",
        message: "Les notes ne doivent pas contenir de HTML ou de code JavaScript."
    )]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $utilisateur = null;
    




    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(int $duree): self { $this->duree = $duree; return $this; }

    public function getCalories(): ?int { return $this->calories; }
    public function setCalories(int $calories): self { $this->calories = $calories; return $this; }

    public function getDateActivite(): ?\DateTimeImmutable { return $this->dateActivite; }
    public function setDateActivite(?\DateTimeImmutable $dateActivite): self { $this->dateActivite = $dateActivite; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

   public function getUtilisateur(): ?User
{
    return $this->utilisateur;
}

public function setUtilisateur(?User $utilisateur): self
{
    $this->utilisateur = $utilisateur;
    return $this;
}
}
