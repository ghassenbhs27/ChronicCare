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
    private string $nom;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'La description doit contenir au moins 10 caracteres.',
        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
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
    private string $categorie;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    private \DateTimeImmutable $createdAt;

    // ==================== CHAMPS MÉDICAUX ====================

    /**
     * Ingrédients actifs du produit (pour vérification interactions)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ingredientsActifs = [];

    /**
     * Contre-indications (conditions où le produit ne doit pas être utilisé)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contreIndications = [];

    /**
     * Interactions médicamenteuses connues
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $interactions = [];

    /**
     * Allergènes présents dans le produit
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allergenes = [];

    /**
     * Indications thérapeutiques (pour quelles conditions le produit est recommandé)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $indicationsTherapeutiques = null;

    /**
     * Objectifs santé associés (perte_poids, energie, sommeil, immunite, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $objectifsAssocies = [];

    /**
     * Score de popularité (calculé automatiquement basé sur les ventes)
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $popularite = 0;

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

    // ==================== GETTERS / SETTERS MÉDICAUX ====================

    public function getIngredientsActifs(): array
    {
        return $this->ingredientsActifs ?? [];
    }

    public function setIngredientsActifs(?array $ingredientsActifs): self
    {
        $this->ingredientsActifs = $ingredientsActifs;
        return $this;
    }

    public function getContreIndications(): array
    {
        return $this->contreIndications ?? [];
    }

    public function setContreIndications(?array $contreIndications): self
    {
        $this->contreIndications = $contreIndications;
        return $this;
    }

    public function getInteractions(): array
    {
        return $this->interactions ?? [];
    }

    public function setInteractions(?array $interactions): self
    {
        $this->interactions = $interactions;
        return $this;
    }

    public function getAllergenes(): array
    {
        return $this->allergenes ?? [];
    }

    public function setAllergenes(?array $allergenes): self
    {
        $this->allergenes = $allergenes;
        return $this;
    }

    public function getIndicationsTherapeutiques(): ?string
    {
        return $this->indicationsTherapeutiques;
    }

    public function setIndicationsTherapeutiques(?string $indicationsTherapeutiques): self
    {
        $this->indicationsTherapeutiques = $indicationsTherapeutiques;
        return $this;
    }

    public function getObjectifsAssocies(): array
    {
        return $this->objectifsAssocies ?? [];
    }

    public function setObjectifsAssocies(?array $objectifsAssocies): self
    {
        $this->objectifsAssocies = $objectifsAssocies;
        return $this;
    }

    public function getPopularite(): int
    {
        return $this->popularite;
    }

    public function setPopularite(int $popularite): self
    {
        $this->popularite = $popularite;
        return $this;
    }

    public function incrementPopularite(int $amount = 1): self
    {
        $this->popularite += $amount;
        return $this;
    }

    /**
     * Vérifie si le produit contient un allergène spécifique
     */
    public function hasAllergene(string $allergene): bool
    {
        return in_array(strtolower($allergene), array_map('strtolower', $this->allergenes ?? []));
    }

    /**
     * Vérifie si le produit a une contre-indication spécifique
     */
    public function hasContreIndication(string $condition): bool
    {
        return in_array(strtolower($condition), array_map('strtolower', $this->contreIndications ?? []));
    }

    /**
     * Vérifie si le produit interagit avec un médicament
     */
    public function hasInteraction(string $medicament): bool
    {
        return in_array(strtolower($medicament), array_map('strtolower', $this->interactions ?? []));
    }

    /**
     * Vérifie si le produit correspond à un objectif santé
     */
    public function matchesObjectif(string $objectif): bool
    {
        return in_array(strtolower($objectif), array_map('strtolower', $this->objectifsAssocies ?? []));
    }
}
