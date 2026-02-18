<?php

namespace App\Entity;

use App\Repository\UrgenceAlerteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UrgenceAlerteRepository::class)]
class UrgenceAlerte
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $patient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $professionnel = null;

    #[ORM\Column(length: 32)]
    private string $targetRole = '';

    #[ORM\Column(length: 255)]
    private string $meetingUrl = '';

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getProfessionnel(): ?User
    {
        return $this->professionnel;
    }

    public function setProfessionnel(?User $professionnel): self
    {
        $this->professionnel = $professionnel;
        return $this;
    }

    public function getTargetRole(): string
    {
        return $this->targetRole;
    }

    public function setTargetRole(string $targetRole): self
    {
        $this->targetRole = $targetRole;
        return $this;
    }

    public function getMeetingUrl(): string
    {
        return $this->meetingUrl;
    }

    public function setMeetingUrl(string $meetingUrl): self
    {
        $this->meetingUrl = $meetingUrl;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(?\DateTimeImmutable $viewedAt): self
    {
        $this->viewedAt = $viewedAt;
        return $this;
    }
}
