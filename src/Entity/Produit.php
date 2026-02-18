<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le nom du produit est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 200,
        minMessage: 'Le nom doit contenir au moins 3 caracteres.',
        maxMessage: 'Le nom du produit ne peut pas depasser {{ limit }} caracteres.'
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\s\-àâäéèêëïîôöùûüœæçÀÂÄÉÈÊËÏÎÔÖÙÛÜŒÆÇ]+$/",
        message: "Le nom ne peut contenir que des lettres, chiffres et tirets."
    )]
    )]
    private string $nom;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'La description doit contenir au moins 10 caracteres.',
        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
    )]
    )]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Type(type: 'numeric', message: 'Le prix doit etre un nombre valide.')]
    #[Assert\GreaterThan(value: 0, message: 'Le prix doit etre superieur a 0.')]
    #[Assert\LessThanOrEqual(value: 9999.99, message: 'Le prix ne peut pas depasser 9999,99.')]
    private string $prix;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le stock est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le stock ne peut pas etre negatif.')]
    #[Assert\LessThanOrEqual(value: 999999, message: 'Le stock est limite a 999999 unites.')]
    private int $stock;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La categorie est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'La categorie doit contenir au moins 2 caracteres.',
        maxMessage: 'La categorie ne peut pas depasser {{ limit }} caracteres.'
    )]
    )]
    private string $categorie;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
    }

    // =========================
    // GETTERS / SETTERS
    // =========================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = $stock;
        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
