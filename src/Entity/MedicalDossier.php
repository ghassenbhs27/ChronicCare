<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\MedicalDossierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedicalDossierRepository::class)]
#[ORM\Table(name: 'medical_dossier')]
#[ORM\UniqueConstraint(name: 'uniq_medical_dossier_user', columns: ['user_id'])]
class MedicalDossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 120)]
    private ?string $disease = null;

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $questionnaire = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $answers = [];

    /** @var array<int, array<string, mixed>> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $diseaseHistory = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiSummary = null;

    #[ORM\Column(length: 20, options: ['default' => 'unknown'])]
    private string $emergencyRiskLevel = 'unknown';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $emergencyPossible = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emergencyReason = null;

    /** @var array<int, string> */
    #[ORM\Column(type: 'json')]
    private array $recommendedActions = [];

    #[ORM\Column(length: 40, options: ['default' => 'FALLBACK'])]
    private string $aiMode = 'FALLBACK';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getDisease(): ?string
    {
        return $this->disease;
    }

    public function setDisease(string $disease): self
    {
        $this->disease = $disease;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuestionnaire(): array
    {
        return $this->questionnaire;
    }

    /**
     * @param array<int, array<string, mixed>> $questionnaire
     */
    public function setQuestionnaire(array $questionnaire): self
    {
        $this->questionnaire = $questionnaire;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     */
    public function setAnswers(array $answers): self
    {
        $this->answers = $answers;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDiseaseHistory(): array
    {
        return $this->diseaseHistory;
    }

    /**
     * @param array<int, array<string, mixed>> $diseaseHistory
     */
    public function setDiseaseHistory(array $diseaseHistory): self
    {
        $this->diseaseHistory = array_values($diseaseHistory);

        return $this;
    }

    public function getAiSummary(): ?string
    {
        return $this->aiSummary;
    }

    public function setAiSummary(?string $aiSummary): self
    {
        $this->aiSummary = $aiSummary;

        return $this;
    }

    public function getEmergencyRiskLevel(): string
    {
        return $this->emergencyRiskLevel;
    }

    public function setEmergencyRiskLevel(string $emergencyRiskLevel): self
    {
        $this->emergencyRiskLevel = $emergencyRiskLevel;

        return $this;
    }

    public function isEmergencyPossible(): bool
    {
        return $this->emergencyPossible;
    }

    public function setEmergencyPossible(bool $emergencyPossible): self
    {
        $this->emergencyPossible = $emergencyPossible;

        return $this;
    }

    public function getEmergencyReason(): ?string
    {
        return $this->emergencyReason;
    }

    public function setEmergencyReason(?string $emergencyReason): self
    {
        $this->emergencyReason = $emergencyReason;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRecommendedActions(): array
    {
        return $this->recommendedActions;
    }

    /**
     * @param array<int, string> $recommendedActions
     */
    public function setRecommendedActions(array $recommendedActions): self
    {
        $this->recommendedActions = $recommendedActions;

        return $this;
    }

    public function getAiMode(): string
    {
        return $this->aiMode;
    }

    public function setAiMode(string $aiMode): self
    {
        $this->aiMode = $aiMode;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

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

    /**
     * @return array<int, string>
     */
    public function getKnownDiseases(): array
    {
        $diseases = [];
        foreach ($this->diseaseHistory as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $disease = strtolower(trim((string) ($entry['disease'] ?? '')));
            if ($disease !== '') {
                $diseases[] = $disease;
            }
        }

        if ($this->disease !== null && trim($this->disease) !== '') {
            $diseases[] = strtolower(trim($this->disease));
        }

        return array_values(array_unique($diseases));
    }

    public function ensureDiseaseHistoryInitialized(string $source = 'system'): self
    {
        if ($this->diseaseHistory !== []) {
            return $this;
        }

        $disease = strtolower(trim((string) $this->disease));
        if ($disease === '') {
            return $this;
        }

        $createdAt = $this->createdAt ?? new \DateTimeImmutable();
        $entry = [
            'disease' => $disease,
            'questionnaire' => $this->questionnaire,
            'answers' => $this->answers,
            'analysis' => [
                'summary' => (string) ($this->aiSummary ?? ''),
                'risk_level' => $this->emergencyRiskLevel,
                'emergency_possible' => $this->emergencyPossible,
                'reason' => (string) ($this->emergencyReason ?? ''),
                'recommended_actions' => $this->recommendedActions,
                'mode' => $this->aiMode,
            ],
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => ($this->updatedAt ?? $createdAt)->format('Y-m-d H:i:s'),
            'source' => trim($source) !== '' ? $source : 'system',
        ];

        $this->diseaseHistory = [$entry];

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $questionnaire
     * @param array<int, array<string, mixed>> $answers
     * @param array<string, mixed> $analysis
     */
    public function upsertDiseaseRecord(
        string $disease,
        array $questionnaire,
        array $answers,
        array $analysis,
        string $source = 'patient'
    ): bool {
        $normalizedDisease = strtolower(trim($disease));
        if ($normalizedDisease === '') {
            $normalizedDisease = 'autre';
        }

        $this->ensureDiseaseHistoryInitialized('legacy');

        $now = new \DateTimeImmutable();
        $recommendedActions = $this->normalizeStringList($analysis['recommended_actions'] ?? []);
        $entry = [
            'disease' => $normalizedDisease,
            'questionnaire' => $questionnaire,
            'answers' => $answers,
            'analysis' => [
                'summary' => trim((string) ($analysis['summary'] ?? '')),
                'risk_level' => trim((string) ($analysis['risk_level'] ?? 'unknown')),
                'emergency_possible' => (bool) ($analysis['emergency_possible'] ?? false),
                'reason' => trim((string) ($analysis['reason'] ?? '')),
                'recommended_actions' => $recommendedActions,
                'mode' => trim((string) ($analysis['mode'] ?? 'FALLBACK')),
            ],
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'source' => trim($source) !== '' ? $source : 'patient',
        ];

        $history = $this->diseaseHistory;
        $targetIndex = null;
        foreach ($history as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemDisease = strtolower(trim((string) ($item['disease'] ?? '')));
            if ($itemDisease === $normalizedDisease) {
                $targetIndex = $index;
                break;
            }
        }

        $added = false;
        if ($targetIndex === null) {
            $entry['created_at'] = $now->format('Y-m-d H:i:s');
            $history[] = $entry;
            $added = true;
        } else {
            $entry['created_at'] = (string) ($history[$targetIndex]['created_at'] ?? $now->format('Y-m-d H:i:s'));
            $history[$targetIndex] = $entry;
        }

        $this->diseaseHistory = array_values($history);
        $this->disease = $normalizedDisease;
        $this->questionnaire = $questionnaire;
        $this->answers = $answers;
        $this->aiSummary = (string) ($entry['analysis']['summary'] ?? '');
        $this->emergencyRiskLevel = (string) ($entry['analysis']['risk_level'] ?? 'unknown');
        $this->emergencyPossible = (bool) ($entry['analysis']['emergency_possible'] ?? false);
        $this->emergencyReason = (string) ($entry['analysis']['reason'] ?? '');
        $this->recommendedActions = $recommendedActions;
        $this->aiMode = (string) ($entry['analysis']['mode'] ?? 'FALLBACK');
        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;

        return $added;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $items[] = $item;
        }

        return array_values(array_unique($items));
    }
}
