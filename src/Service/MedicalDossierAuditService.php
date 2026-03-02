<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\MedicalDossier;
use App\Entity\MedicalDossierAuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class MedicalDossierAuditService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $changedFields
     * @param array<string, mixed> $context
     */
    public function log(
        MedicalDossier $dossier,
        ?User $actor,
        string $action,
        ?Request $request = null,
        ?string $targetDisease = null,
        array $changedFields = [],
        array $context = [],
        bool $flush = true
    ): void {
        $auditLog = (new MedicalDossierAuditLog())
            ->setDossier($dossier)
            ->setActor($actor)
            ->setActorEmail($actor?->getEmail())
            ->setActorRole($this->resolveActorRole($actor))
            ->setAction($action)
            ->setTargetDisease($targetDisease)
            ->setChangedFields($changedFields !== [] ? $changedFields : null)
            ->setContext($context !== [] ? $context : null)
            ->setIpAddress($request?->getClientIp())
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($auditLog);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function resolveActorRole(?User $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        foreach ($actor->getRoles() as $role) {
            if ($role !== 'ROLE_USER') {
                return $role;
            }
        }

        return 'ROLE_USER';
    }
}
