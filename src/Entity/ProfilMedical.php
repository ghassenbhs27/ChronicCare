<?php

namespace App\Entity;

use App\Repository\ProfilMedicalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Profil médical du patient pour les recommandations personnalisées
 */
#[ORM\Entity(repositoryClass: ProfilMedicalRepository::class)]
class ProfilMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profilMedical', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $utilisateur;

    /**
     * Conditions chroniques (diabète, hypertension, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $conditionsChroniques = [];

    /**
     * Allergies connues (pénicilline, lactose, gluten, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allergies = [];

    /**
     * Médicaments actuellement pris
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $medicamentsActuels = [];

    /**
     * Objectifs de santé (perte de poids, énergie, sommeil, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $objectifsSante = [];

    /**
     * Régime alimentaire (végétarien, végan, sans gluten, etc.)
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $regimeAlimentaire = null;

    /**
     * Tranche d'âge pour recommandations adaptées
     */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['18-30', '31-45', '46-60', '60+'], message: "Tranche d'âge invalide.")]
    private ?string $trancheAge = null;

    /**
     * Niveau d'activité physique
     */
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(choices: ['sedentaire', 'leger', 'modere', 'actif', 'sportif'], message: "Niveau d'activité invalide.")]
    private ?string $niveauActivite = null;

    /**
     * Notes additionnelles du médecin/nutritionniste
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notesMedicales = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->conditionsChroniques = [];
        $this->allergies = [];
        $this->medicamentsActuels = [];
        $this->objectifsSante = [];
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getConditionsChroniques(): array
    {
        return $this->conditionsChroniques ?? [];
    }

    public function setConditionsChroniques(?array $conditionsChroniques): self
    {
        $this->conditionsChroniques = $conditionsChroniques;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function addConditionChronique(string $condition): self
    {
        if (!in_array($condition, $this->conditionsChroniques ?? [])) {
            $this->conditionsChroniques[] = $condition;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getAllergies(): array
    {
        return $this->allergies ?? [];
    }

    public function setAllergies(?array $allergies): self
    {
        $this->allergies = $allergies;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function addAllergie(string $allergie): self
    {
        if (!in_array($allergie, $this->allergies ?? [])) {
            $this->allergies[] = $allergie;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getMedicamentsActuels(): array
    {
        return $this->medicamentsActuels ?? [];
    }

    public function setMedicamentsActuels(?array $medicamentsActuels): self
    {
        $this->medicamentsActuels = $medicamentsActuels;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function addMedicamentActuel(string $medicament): self
    {
        if (!in_array($medicament, $this->medicamentsActuels ?? [])) {
            $this->medicamentsActuels[] = $medicament;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getObjectifsSante(): array
    {
        return $this->objectifsSante ?? [];
    }

    public function setObjectifsSante(?array $objectifsSante): self
    {
        $this->objectifsSante = $objectifsSante;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRegimeAlimentaire(): ?string
    {
        return $this->regimeAlimentaire;
    }

    public function setRegimeAlimentaire(?string $regimeAlimentaire): self
    {
        $this->regimeAlimentaire = $regimeAlimentaire;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTrancheAge(): ?string
    {
        return $this->trancheAge;
    }

    public function setTrancheAge(?string $trancheAge): self
    {
        $this->trancheAge = $trancheAge;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getNiveauActivite(): ?string
    {
        return $this->niveauActivite;
    }

    public function setNiveauActivite(?string $niveauActivite): self
    {
        $this->niveauActivite = $niveauActivite;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getNotesMedicales(): ?string
    {
        return $this->notesMedicales;
    }

    public function setNotesMedicales(?string $notesMedicales): self
    {
        $this->notesMedicales = $notesMedicales;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Retourne un résumé du profil médical pour l'IA
     */
    public function toSummaryString(): string
    {
        $parts = [];
        
        if (!empty($this->conditionsChroniques)) {
            $parts[] = "Conditions: " . implode(', ', $this->conditionsChroniques);
        }
        if (!empty($this->allergies)) {
            $parts[] = "Allergies: " . implode(', ', $this->allergies);
        }
        if (!empty($this->medicamentsActuels)) {
            $parts[] = "Médicaments: " . implode(', ', $this->medicamentsActuels);
        }
        if (!empty($this->objectifsSante)) {
            $parts[] = "Objectifs: " . implode(', ', $this->objectifsSante);
        }
        if ($this->regimeAlimentaire) {
            $parts[] = "Régime: " . $this->regimeAlimentaire;
        }
        if ($this->niveauActivite) {
            $parts[] = "Activité: " . $this->niveauActivite;
        }

        return implode(' | ', $parts) ?: 'Profil non renseigné';
    }
}
