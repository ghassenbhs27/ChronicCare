<?php

namespace App\Entity;

use App\Repository\RDVRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RDVRepository::class)]
class RDV
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rDVs')]
    #[ORM\JoinColumn(nullable: false)]
    private User $patient;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $professionnel;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateRdv;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 50)]
    private string $statut;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

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
