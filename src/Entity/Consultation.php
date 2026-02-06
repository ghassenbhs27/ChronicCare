<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'consultation')]
    #[ORM\JoinColumn(nullable: false)]
    private RDV $rdv;

    #[ORM\Column(type: Types::TEXT)]
    private string $notes;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
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
