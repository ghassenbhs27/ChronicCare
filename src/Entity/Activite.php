<?php

namespace App\Entity;

use App\Repository\ActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
class Activite
{
    public const TYPE_CHOICES = ['marche', 'course', 'velo'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activites')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'activites')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Etat $etat = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le type d activite est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le type d activite ne peut pas depasser {{ limit }} caracteres.'
    )]
    #[Assert\Choice(choices: self::TYPE_CHOICES, message: 'Le type doit etre marche, course ou velo.')]
    private string $type;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La duree est obligatoire.')]
    #[Assert\Positive(message: 'La duree doit etre superieure a 0.')]
    private ?int $duree = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le nombre de calories est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le nombre de calories doit etre superieur ou egal a 0.')]
    private ?int $calories = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La distance doit etre superieure ou egale a 0.')]
    private ?float $distanceKm = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Les heures de repos doivent etre superieures ou egales a 0.')]
    private ?int $heuresRepos = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de l activite est obligatoire.')]
    private ?\DateTimeImmutable $dateActivite = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes ne peuvent pas depasser {{ limit }} caracteres.'
    )]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getEtat(): ?Etat
    {
        return $this->etat;
    }

    public function setEtat(?Etat $etat): self
    {
        $this->etat = $etat;
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

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(?int $duree): self
    {
        $this->duree = $duree;
        return $this;
    }

    public function getCalories(): ?int
    {
        return $this->calories;
    }

    public function setCalories(?int $calories): self
    {
        $this->calories = $calories;
        return $this;
    }

    public function getDistanceKm(): ?float
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(?float $distanceKm): self
    {
        $this->distanceKm = $distanceKm;
        return $this;
    }

    public function getHeuresRepos(): ?int
    {
        return $this->heuresRepos;
    }

    public function setHeuresRepos(?int $heuresRepos): self
    {
        $this->heuresRepos = $heuresRepos;
        return $this;
    }

    public function getDateActivite(): ?\DateTimeImmutable
    {
        return $this->dateActivite;
    }

    public function setDateActivite(?\DateTimeImmutable $dateActivite): self
    {
        $this->dateActivite = $dateActivite;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
