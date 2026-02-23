<?php

namespace App\Entity;

use App\Repository\RDVRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RDVRepository::class)]
class RDV
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_CONFIRME = 'confirme';
    public const STATUT_ANNULE = 'annule';
    public const STATUT_TERMINE = 'termine';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le patient est obligatoire.')]
    private User $patient;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le professionnel est obligatoire.')]
    private User $professionnel;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date du rendez-vous est obligatoire.')]
    private \DateTimeInterface $dateRdv;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le motif ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les conseils ne peuvent pas depasser {{ limit }} caracteres.'
    )]
    private ?string $conseilsPreconsultation = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUT_EN_ATTENTE, self::STATUT_CONFIRME, self::STATUT_ANNULE, self::STATUT_TERMINE],
        message: 'Le statut selectionne est invalide.'
    )]
    private string $statut;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de creation est obligatoire.')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    #[ORM\OneToOne(mappedBy: 'rdv', cascade: ['persist', 'remove'])]
    private ?Consultation $consultation = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->statut = 'confirme';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): User
    {
        return $this->patient;
    }

    public function setPatient(User $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function getProfessionnel(): User
    {
        return $this->professionnel;
    }

    public function setProfessionnel(User $professionnel): self
    {
        $this->professionnel = $professionnel;
        return $this;
    }

    public function getDateRdv(): \DateTimeInterface
    {
        return $this->dateRdv;
    }

    public function setDateRdv(\DateTimeInterface $dateRdv): self
    {
        $this->dateRdv = $dateRdv;
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): self
    {
        $this->motif = $motif;
        return $this;
    }

    public function getConseilsPreconsultation(): ?string
    {
        return $this->conseilsPreconsultation;
    }

    public function setConseilsPreconsultation(?string $conseilsPreconsultation): self
    {
        $this->conseilsPreconsultation = $conseilsPreconsultation;
        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): self
    {
        $this->reminderSentAt = $reminderSentAt;
        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(Consultation $consultation): static
    {
        // set the owning side of the relation if necessary
        if ($consultation->getRdv() !== $this) {
            $consultation->setRdv($this);
        }

        $this->consultation = $consultation;

        return $this;
    }
}
