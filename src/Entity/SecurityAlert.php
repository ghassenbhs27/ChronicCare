<?php

namespace App\Entity;

use App\Repository\SecurityAlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecurityAlertRepository::class)]
#[ORM\Table(name: 'security_alert')]
#[ORM\Index(columns: ['event_type', 'created_at'], name: 'idx_security_alert_type_time')]
#[ORM\Index(columns: ['is_resolved', 'created_at'], name: 'idx_security_alert_resolved_time')]
#[ORM\Index(columns: ['alert_type', 'created_at'], name: 'idx_security_alert_alert_type_time')]
#[ORM\Index(columns: ['alert_status', 'created_at'], name: 'idx_security_alert_alert_status_time')]
class SecurityAlert
{
    public const CATEGORY_MEDICALE = 'medicale';
    public const CATEGORY_COMPORTEMENTALE = 'comportementale';
    public const CATEGORY_SECURITAIRE = 'securitaire';
    public const CATEGORY_TECHNIQUE = 'technique';

    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_TRAITEE = 'traitee';
    public const STATUS_IGNOREE = 'ignoree';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 64)]
    private string $eventType;

    #[ORM\Column(length: 30, options: ['default' => self::CATEGORY_SECURITAIRE])]
    private string $alertType = self::CATEGORY_SECURITAIRE;

    #[ORM\Column(length: 20)]
    private string $severity = 'medium';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $riskScore = 0;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_EN_COURS])]
    private string $alertStatus = self::STATUS_EN_COURS;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $statusHistory = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isResolved = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getAlertType(): string
    {
        return $this->alertType;
    }

    public function setAlertType(string $alertType): self
    {
        $allowed = [
            self::CATEGORY_MEDICALE,
            self::CATEGORY_COMPORTEMENTALE,
            self::CATEGORY_SECURITAIRE,
            self::CATEGORY_TECHNIQUE,
        ];

        $normalized = strtolower(trim($alertType));
        $this->alertType = in_array($normalized, $allowed, true) ? $normalized : self::CATEGORY_SECURITAIRE;

        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function getRiskScore(): int
    {
        return $this->riskScore;
    }

    public function setRiskScore(int $riskScore): self
    {
        $this->riskScore = max(0, min(100, $riskScore));

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAlertStatus(): string
    {
        return $this->alertStatus;
    }

    public function setAlertStatus(string $alertStatus): self
    {
        $allowed = [self::STATUS_EN_COURS, self::STATUS_TRAITEE, self::STATUS_IGNOREE];
        $normalized = strtolower(trim($alertStatus));
        $this->alertStatus = in_array($normalized, $allowed, true) ? $normalized : self::STATUS_EN_COURS;

        if ($this->alertStatus === self::STATUS_EN_COURS) {
            $this->isResolved = false;
            $this->resolvedAt = null;
        } else {
            $this->isResolved = true;
            if ($this->resolvedAt === null) {
                $this->resolvedAt = new \DateTimeImmutable();
            }
        }

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStatusHistory(): array
    {
        return $this->statusHistory ?? [];
    }

    public function setStatusHistory(?array $statusHistory): self
    {
        if ($statusHistory === null) {
            $this->statusHistory = null;

            return $this;
        }

        $this->statusHistory = array_values($statusHistory);

        return $this;
    }

    public function addStatusHistoryEntry(string $status, string $source = 'system', ?string $note = null): self
    {
        $history = $this->getStatusHistory();
        $entry = [
            'status' => $status,
            'changed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'source' => trim($source) !== '' ? $source : 'system',
        ];
        if ($note !== null && trim($note) !== '') {
            $entry['note'] = trim($note);
        }

        $history[] = $entry;
        $this->statusHistory = $history;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
        if ($resolvedAt !== null) {
            $this->isResolved = true;
            if ($this->alertStatus === self::STATUS_EN_COURS) {
                $this->alertStatus = self::STATUS_TRAITEE;
            }
        } elseif ($this->alertStatus === self::STATUS_EN_COURS) {
            $this->isResolved = false;
        }

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function setIsResolved(bool $isResolved): self
    {
        $this->isResolved = $isResolved;
        if ($isResolved) {
            if ($this->alertStatus === self::STATUS_EN_COURS) {
                $this->alertStatus = self::STATUS_TRAITEE;
            }
            if ($this->resolvedAt === null) {
                $this->resolvedAt = new \DateTimeImmutable();
            }
        } else {
            $this->alertStatus = self::STATUS_EN_COURS;
            $this->resolvedAt = null;
        }

        return $this;
    }
}
