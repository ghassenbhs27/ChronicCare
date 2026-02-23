<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    // Types de notifications
    public const TYPE_LIKE_PUBLICATION = 'like_publication';
    public const TYPE_LIKE_COMMENTAIRE = 'like_commentaire';
    public const TYPE_COMMENTAIRE = 'commentaire';
    
    public const TYPES = [
        self::TYPE_LIKE_PUBLICATION,
        self::TYPE_LIKE_COMMENTAIRE,
        self::TYPE_COMMENTAIRE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $destinataire;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $emetteur = null;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Publication $publication = null;

    #[ORM\ManyToOne(targetEntity: Commentaire::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Commentaire $commentaire = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): User
    {
        return $this->destinataire;
    }

    public function setDestinataire(User $destinataire): self
    {
        $this->destinataire = $destinataire;
        return $this;
    }

    public function getEmetteur(): ?User
    {
        return $this->emetteur;
    }

    public function setEmetteur(?User $emetteur): self
    {
        $this->emetteur = $emetteur;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type de notification invalide');
        }
        $this->type = $type;
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

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): self
    {
        $this->lien = $lien;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function markAsRead(): self
    {
        $this->isRead = true;
        return $this;
    }

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): self
    {
        $this->publication = $publication;
        return $this;
    }

    public function getCommentaire(): ?Commentaire
    {
        return $this->commentaire;
    }

    public function setCommentaire(?Commentaire $commentaire): self
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_LIKE_PUBLICATION, self::TYPE_LIKE_COMMENTAIRE => 'bi-heart-fill',
            self::TYPE_COMMENTAIRE => 'bi-chat-dots-fill',
            default => 'bi-bell-fill',
        };
    }

    public function getColorClass(): string
    {
        return match ($this->type) {
            self::TYPE_LIKE_PUBLICATION, self::TYPE_LIKE_COMMENTAIRE => 'text-danger',
            self::TYPE_COMMENTAIRE => 'text-primary',
            default => 'text-secondary',
        };
    }

    public function getTimeAgo(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $this->createdAt->getTimestamp();

        if ($diff < 60) {
            return 'À l\'instant';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' heure' . ($hours > 1 ? 's' : '');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' jour' . ($days > 1 ? 's' : '');
        } else {
            return $this->createdAt->format('d/m/Y');
        }
    }
}