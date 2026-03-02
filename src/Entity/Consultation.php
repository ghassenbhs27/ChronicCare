<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le resume ne peut pas depasser {{ limit }} caracteres.'
    )]
    private ?string $resume = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les conseils ne peuvent pas depasser {{ limit }} caracteres.'
    )]
    private ?string $conseils = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de consultation est obligatoire.')]
    private \DateTimeImmutable $dateConsultation;

    #[Vich\UploadableField(mapping: 'consultation_documents', fileNameProperty: 'documentName', size: 'documentSize', mimeType: 'documentMimeType', originalName: 'documentOriginalName')]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/webp',
        ],
        mimeTypesMessage: 'Format de document non supporte.'
    )]
    private ?File $documentFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentOriginalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $documentMimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $documentSize = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $aiGeneratedAt = null;

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

    public function getResume(): ?string
    {
        return $this->resume;
    }

    public function setResume(?string $resume): self
    {
        $this->resume = $resume;
        return $this;
    }

    public function getConseils(): ?string
    {
        return $this->conseils;
    }

    public function setConseils(?string $conseils): self
    {
        $this->conseils = $conseils;
        return $this;
    }

    public function getDateConsultation(): \DateTimeImmutable
    {
        return $this->dateConsultation;
    }

    public function setDocumentFile(?File $documentFile): self
    {
        $this->documentFile = $documentFile;
        if ($documentFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getDocumentFile(): ?File
    {
        return $this->documentFile;
    }

    public function getDocumentName(): ?string
    {
        return $this->documentName;
    }

    public function setDocumentName(?string $documentName): self
    {
        $this->documentName = $documentName;
        return $this;
    }

    public function getDocumentOriginalName(): ?string
    {
        return $this->documentOriginalName;
    }

    public function setDocumentOriginalName(?string $documentOriginalName): self
    {
        $this->documentOriginalName = $documentOriginalName;
        return $this;
    }

    public function getDocumentMimeType(): ?string
    {
        return $this->documentMimeType;
    }

    public function setDocumentMimeType(?string $documentMimeType): self
    {
        $this->documentMimeType = $documentMimeType;
        return $this;
    }

    public function getDocumentSize(): ?int
    {
        return $this->documentSize;
    }

    public function setDocumentSize(?int $documentSize): self
    {
        $this->documentSize = $documentSize;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAiGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->aiGeneratedAt;
    }

    public function setAiGeneratedAt(?\DateTimeImmutable $aiGeneratedAt): self
    {
        $this->aiGeneratedAt = $aiGeneratedAt;
        return $this;
    }
}
