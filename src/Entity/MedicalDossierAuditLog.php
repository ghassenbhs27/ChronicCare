<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\MedicalDossierAuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedicalDossierAuditLogRepository::class)]
#[ORM\Table(name: 'medical_dossier_audit_log')]
#[ORM\Index(columns: ['action', 'created_at'], name: 'idx_medical_audit_action_time')]
#[ORM\Index(columns: ['ip_address', 'created_at'], name: 'idx_medical_audit_ip_time')]
class MedicalDossierAuditLog
{
    public const ACTION_CONSULTATION = 'consultation';
    public const ACTION_CREATION = 'creation';
    public const ACTION_MODIFICATION = 'modification';
    public const ACTION_AJOUT_MALADIE = 'ajout_maladie';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MedicalDossier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MedicalDossier $dossier = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $actorRole = null;

    #[ORM\Column(length: 40)]
    private string $action = self::ACTION_CONSULTATION;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $targetDisease = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changedFields = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDossier(): ?MedicalDossier
    {
        return $this->dossier;
    }

    public function setDossier(?MedicalDossier $dossier): self
    {
        $this->dossier = $dossier;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): self
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getActorRole(): ?string
    {
        return $this->actorRole;
    }

    public function setActorRole(?string $actorRole): self
    {
        $this->actorRole = $actorRole;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = strtolower(trim($action));

        return $this;
    }

    public function getTargetDisease(): ?string
    {
        return $this->targetDisease;
    }

    public function setTargetDisease(?string $targetDisease): self
    {
        if ($targetDisease === null) {
            $this->targetDisease = null;

            return $this;
        }

        $targetDisease = strtolower(trim($targetDisease));
        $this->targetDisease = $targetDisease !== '' ? $targetDisease : null;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }

    public function setChangedFields(?array $changedFields): self
    {
        $this->changedFields = $changedFields;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): self
    {
        $this->context = $context;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
