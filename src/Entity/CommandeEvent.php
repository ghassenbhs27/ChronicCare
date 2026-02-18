<?php

namespace App\Entity;

use App\Repository\CommandeEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Event Sourcing : Stockage de tous les événements liés aux commandes
 * Permet de reconstruire l'historique complet et d'auditer toutes les actions
 */
#[ORM\Entity(repositoryClass: CommandeEventRepository::class)]
#[ORM\Index(columns: ['commande_id', 'created_at'], name: 'idx_commande_date')]
#[ORM\Index(columns: ['event_type'], name: 'idx_event_type')]
class CommandeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(length: 100)]
    private string $eventType;

    #[ORM\Column(type: Types::JSON)]
    private array $eventData = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $triggeredBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ==========================================
    // GETTERS / SETTERS
    // ==========================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function setCommande(Commande $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getEventData(): array
    {
        return $this->eventData;
    }

    public function setEventData(array $eventData): static
    {
        $this->eventData = $eventData;
        return $this;
    }

    public function getTriggeredBy(): ?User
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?User $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): static
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): static
    {
        $this->newValue = $newValue;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Formatte l'événement pour l'affichage
     */
    public function getFormattedMessage(): string
    {
        return match ($this->eventType) {
            'commande.created' => 'Commande créée',
            'commande.status_changed' => sprintf(
                'Statut changé de "%s" vers "%s"',
                $this->oldValue ?? 'N/A',
                $this->newValue ?? 'N/A'
            ),
            'commande.payment_processed' => 'Paiement traité',
            'commande.shipped' => 'Commande expédiée',
            'commande.delivered' => 'Commande livrée',
            'commande.cancelled' => 'Commande annulée',
            'commande.refunded' => 'Commande remboursée',
            'commande.updated' => 'Commande modifiée',
            default => $this->eventType,
        };
    }

    /**
     * Retourne la classe CSS pour le badge d'événement
     */
    public function getBadgeClass(): string
    {
        return match ($this->eventType) {
            'commande.created' => 'badge bg-primary',
            'commande.status_changed' => 'badge bg-info',
            'commande.payment_processed' => 'badge bg-success',
            'commande.shipped' => 'badge bg-warning',
            'commande.delivered' => 'badge bg-success',
            'commande.cancelled' => 'badge bg-danger',
            'commande.refunded' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }

    /**
     * Retourne l'icône Bootstrap Icons appropriée
     */
    public function getIcon(): string
    {
        return match ($this->eventType) {
            'commande.created' => 'bi-plus-circle',
            'commande.status_changed' => 'bi-arrow-left-right',
            'commande.payment_processed' => 'bi-credit-card',
            'commande.shipped' => 'bi-box-seam',
            'commande.delivered' => 'bi-check2-all',
            'commande.cancelled' => 'bi-x-circle',
            'commande.refunded' => 'bi-arrow-counterclockwise',
            default => 'bi-info-circle',
        };
    }
}
