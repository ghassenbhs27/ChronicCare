<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'consultation')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le rendez-vous est obligatoire.')]
    private RDV $rdv;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les notes sont obligatoires.')]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Les notes ne peuvent pas depasser {{ limit }} caracteres.'
    )]
    private string $notes;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le diagnostic ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de consultation est obligatoire.')]
    private \DateTimeImmutable $dateConsultation;

    public function __construct()
    {
        $this->dateConsultation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRdv(): RDV
    {
        return $this->rdv;
    }

    public function setRdv(RDV $rdv): self
    {
        $this->rdv = $rdv;
        return $this;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDiagnostic(): ?string
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?string $diagnostic): self
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getDateConsultation(): \DateTimeImmutable
    {
        return $this->dateConsultation;
    }
}
