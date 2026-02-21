<?php

namespace App\Entity;

use App\Repository\SuiviAnalyseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuiviAnalyseRepository::class)]
class SuiviAnalyse
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $patient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $auteur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $periodStart;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $periodEnd;

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    /** @var array<int,array<string,mixed>> */
    #[ORM\Column(type: Types::JSON)]
    private array $etatSnapshot = [];

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->periodEnd = $now;
        $this->periodStart = $now->modify('-6 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): self
    {
        $this->auteur = $auteur;
        return $this;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): self
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): self
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getEtatSnapshot(): array
    {
        if (!isset($this->etatSnapshot)) {
            return [];
        }

        return $this->etatSnapshot;
    }

    /**
     * @param array<int,array<string,mixed>> $etatSnapshot
     */
    public function setEtatSnapshot(array $etatSnapshot): self
    {
        $this->etatSnapshot = $etatSnapshot;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
