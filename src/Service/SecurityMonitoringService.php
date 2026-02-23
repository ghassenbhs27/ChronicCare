<?php

namespace App\Service;

use App\Entity\SecurityAlert;
use App\Entity\SecurityEventLog;
use App\Entity\User;
use App\Repository\SecurityAlertRepository;
use App\Repository\SecurityEventLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SecurityMonitoringService
{
    public const EVENT_FAILED_LOGIN = 'failed_login';
    public const EVENT_RESET_PASSWORD_REQUEST = 'reset_password_request';
    public const EVENT_EMAIL_CHANGE = 'email_change';
    public const EVENT_USER_ACTION = 'user_action';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecurityEventLogRepository $eventLogRepository,
        private readonly SecurityAlertRepository $alertRepository,
        private readonly UserRepository $userRepository,
        private readonly NotificationService $notificationService
    ) {
    }

    public function trackFailedLogin(Request $request, ?User $user, ?string $identifier): void
    {
        $count = $this->persistEventAndCount(
            self::EVENT_FAILED_LOGIN,
            $request,
            $user,
            $identifier,
            ['reason' => 'authentication_failure'],
            new \DateTimeImmutable('-10 minutes')
        );

        if ($count >= 5) {
            $this->createAlert(
                eventType: self::EVENT_FAILED_LOGIN,
                request: $request,
                user: $user,
                count: $count,
                severity: 'high',
                message: sprintf('Plusieurs echecs de connexion detectes (%d en 10 minutes).', $count),
                context: ['identifier' => $identifier],
                blockUser: true
            );
        }
    }

    public function trackResetPasswordRequest(Request $request, ?User $user, string $identifier): void
    {
        $count = $this->persistEventAndCount(
            self::EVENT_RESET_PASSWORD_REQUEST,
            $request,
            $user,
            $identifier,
            ['target' => $identifier],
            new \DateTimeImmutable('-15 minutes')
        );

        if ($count >= 3) {
            $this->createAlert(
                eventType: self::EVENT_RESET_PASSWORD_REQUEST,
                request: $request,
                user: $user,
                count: $count,
                severity: 'high',
                message: sprintf('Trop de demandes de reset password (%d en 15 minutes).', $count),
                context: ['identifier' => $identifier],
                // Reset password abuse should be alerted, but should not lock the account itself.
                blockUser: false
            );
        }
    }

    public function trackEmailChange(Request $request, User $user, string $oldEmail, string $newEmail): void
    {
        $count = $this->persistEventAndCount(
            self::EVENT_EMAIL_CHANGE,
            $request,
            $user,
            $newEmail,
            ['old_email' => $oldEmail, 'new_email' => $newEmail],
            new \DateTimeImmutable('-24 hours')
        );

        if ($count >= 3) {
            $this->createAlert(
                eventType: self::EVENT_EMAIL_CHANGE,
                request: $request,
                user: $user,
                count: $count,
                severity: 'critical',
                message: sprintf('Changements d email repetes detectes (%d en 24 heures).', $count),
                context: ['old_email' => $oldEmail, 'new_email' => $newEmail],
                blockUser: true
            );
        }
    }

    public function trackUserAction(Request $request, User $user): void
    {
        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || str_starts_with($route, '_')) {
            return;
        }

        $count = $this->persistEventAndCount(
            self::EVENT_USER_ACTION,
            $request,
            $user,
            null,
            ['route' => $route, 'method' => $request->getMethod()],
            new \DateTimeImmutable('-1 minute')
        );

        if ($count >= 20) {
            $this->createAlert(
                eventType: self::EVENT_USER_ACTION,
                request: $request,
                user: $user,
                count: $count,
                severity: 'critical',
                message: sprintf('Activite anormale detectee (%d actions en 1 minute).', $count),
                context: ['route' => $route, 'method' => $request->getMethod()],
                blockUser: true
            );
        }
    }

    private function persistEventAndCount(
        string $eventType,
        Request $request,
        ?User $user,
        ?string $identifier,
        array $context,
        \DateTimeImmutable $since
    ): int {
        $event = (new SecurityEventLog())
            ->setEventType($eventType)
            ->setUser($user)
            ->setIdentifier($this->normalizeIdentifier($identifier))
            ->setIpAddress($this->extractIp($request))
            ->setRoute((string) $request->attributes->get('_route', null))
            ->setOccurredAt(new \DateTimeImmutable())
            ->setContext($context);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->eventLogRepository->countRecentByType(
            eventType: $eventType,
            since: $since,
            user: $user,
            identifier: $this->normalizeIdentifier($identifier),
            ipAddress: $this->extractIp($request)
        );
    }

    private function createAlert(
        string $eventType,
        Request $request,
        ?User $user,
        int $count,
        string $severity,
        string $message,
        array $context,
        bool $blockUser
    ): void {
        $ip = $this->extractIp($request);
        $recentWindow = new \DateTimeImmutable('-5 minutes');
        if ($this->alertRepository->hasRecentOpenAlert($eventType, $recentWindow, $user, $ip)) {
            return;
        }

        $alert = (new SecurityAlert())
            ->setEventType($eventType)
            ->setAlertType($this->resolveAlertType($eventType, $user, $context))
            ->setAlertStatus(SecurityAlert::STATUS_EN_COURS)
            ->setUser($user)
            ->setSeverity($severity)
            ->setRiskScore($this->calculateRiskScore($eventType, $count, $severity))
            ->setMessage($message)
            ->setIpAddress($ip)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setContext($context)
            ->addStatusHistoryEntry(
                SecurityAlert::STATUS_EN_COURS,
                'system',
                'Alerte creee automatiquement par la surveillance.'
            );

        $this->entityManager->persist($alert);

        if ($blockUser && $user !== null) {
            $this->applyTemporaryBlock($user, 30);
        }

        $this->entityManager->flush();

        $adminEmails = $this->userRepository->findActiveAdminEmails();
        $this->notificationService->sendAdminSecurityAlert($alert, $adminEmails);
    }

    private function applyTemporaryBlock(User $user, int $minutes): void
    {
        $now = new \DateTimeImmutable();
        $blockUntil = $now->modify(sprintf('+%d minutes', $minutes));
        $existing = $user->getSecurityBlockedUntil();

        if ($existing !== null && $existing > $blockUntil) {
            return;
        }

        $user->setSecurityBlockedUntil($blockUntil);
    }

    private function calculateRiskScore(string $eventType, int $count, string $severity): int
    {
        $base = match ($eventType) {
            self::EVENT_FAILED_LOGIN => 35,
            self::EVENT_RESET_PASSWORD_REQUEST => 45,
            self::EVENT_EMAIL_CHANGE => 60,
            self::EVENT_USER_ACTION => 70,
            default => 30,
        };

        $severityBoost = match ($severity) {
            'critical' => 20,
            'high' => 10,
            default => 0,
        };

        return min(100, $base + $severityBoost + ($count * 3));
    }

    private function extractIp(Request $request): ?string
    {
        $ip = $request->getClientIp();

        return $ip !== null && $ip !== '' ? $ip : null;
    }

    private function normalizeIdentifier(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($identifier));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveAlertType(string $eventType, ?User $user, array $context): string
    {
        if (in_array($eventType, [self::EVENT_FAILED_LOGIN, self::EVENT_RESET_PASSWORD_REQUEST, self::EVENT_EMAIL_CHANGE], true)) {
            return SecurityAlert::CATEGORY_SECURITAIRE;
        }

        $route = strtolower((string) ($context['route'] ?? ''));
        if ($route !== '') {
            if ($this->containsAny($route, ['etat', 'medical', 'dossier', 'consultation', 'rdv', 'suivi'])) {
                return SecurityAlert::CATEGORY_MEDICALE;
            }

            if ($this->containsAny($route, ['publication', 'commentaire', 'forum', 'profil', 'profile'])) {
                return SecurityAlert::CATEGORY_COMPORTEMENTALE;
            }

            if ($this->containsAny($route, ['admin', 'api', 'assistant', 'ai', 'security', 'statistique'])) {
                return SecurityAlert::CATEGORY_TECHNIQUE;
            }
        }

        if ($user !== null) {
            $roles = $user->getRoles();
            if (in_array('ROLE_PATIENT', $roles, true)) {
                return SecurityAlert::CATEGORY_MEDICALE;
            }

            if (in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true)) {
                return SecurityAlert::CATEGORY_COMPORTEMENTALE;
            }

            if (in_array('ROLE_ADMIN', $roles, true)) {
                return SecurityAlert::CATEGORY_TECHNIQUE;
            }
        }

        return SecurityAlert::CATEGORY_SECURITAIRE;
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
