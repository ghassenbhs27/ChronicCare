<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_REFUSE = 'refuse';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUT_EN_ATTENTE, self::STATUT_VALIDE, self::STATUT_REFUSE],
        message: 'Le statut selectionne est invalide.'
    )]
    private string $statut = self::STATUT_VALIDE;

    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le coach est obligatoire.')]
    private User $coach;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        max: 200,
        maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La description doit contenir au moins {{ limit }} caracteres.',
        max: 5000,
        maxMessage: 'La description ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $description;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de debut est obligatoire.')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'dateDebut',
        message: 'La date de fin doit etre posterieure a la date de debut.'
    )]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(
        min: 2,
        minMessage: 'Le lieu doit contenir au moins {{ limit }} caracteres.',
        max: 255,
        maxMessage: 'Le lieu ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $lieu;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Exercice>
     */
    #[ORM\OneToMany(targetEntity: Exercice::class, mappedBy: 'evenement')]
    private Collection $exercices;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->exercices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoach(): User
    {
        return $this->coach;
    }

    public function setCoach(User $coach): self
    {
        $this->coach = $coach;
        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
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

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): \DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    public function isValide(): bool
    {
        return $this->statut === self::STATUT_VALIDE;
    }

    /**
     * @return Collection<int, Exercice>
     */
    public function getExercices(): Collection
    {
        return $this->exercices;
    }

    public function addExercice(Exercice $exercice): static
    {
        if (!$this->exercices->contains($exercice)) {
            $this->exercices->add($exercice);
            $exercice->setEvenement($this);
        }

        return $this;
    }

    public function removeExercice(Exercice $exercice): static
    {
        if ($this->exercices->removeElement($exercice)) {
            // set the owning side to null (unless already changed)
            if ($exercice->getEvenement() === $this) {
                $exercice->setEvenement(null);
            }
        }

        return $this;
    }
}
