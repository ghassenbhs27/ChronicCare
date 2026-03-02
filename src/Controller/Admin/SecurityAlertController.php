<?php

namespace App\Controller\Admin;

use App\Entity\SecurityAlert;
use App\Repository\SecurityAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/security-alerts', name: 'admin_security_alert_')]
#[IsGranted('ROLE_ADMIN')]
class SecurityAlertController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        SecurityAlertRepository $alertRepository,
        PaginatorInterface $paginator
    ): Response {
        $status = $this->normalizeStatusFilter((string) $request->query->get('status', SecurityAlert::STATUS_EN_COURS));
        $severity = (string) $request->query->get('severity', '');
        $type = $this->normalizeTypeFilter((string) $request->query->get('type', ''));
        $search = trim((string) $request->query->get('q', ''));
        $sort = $this->normalizeAlertSort((string) $request->query->get('sort', 'createdAt'));
        $direction = $this->normalizeSortDirection((string) $request->query->get('dir', 'DESC'));
        $page = max(1, (int) $request->query->get('page', 1));

        $qb = $alertRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->orderBy($this->mapAlertSortField($sort), $direction)
            ->addOrderBy('a.id', 'DESC');

        if ($status !== 'all') {
            $qb->andWhere('a.alertStatus = :status')
                ->setParameter('status', $status);
        }

        if ($severity !== '') {
            $qb->andWhere('a.severity = :severity')
                ->setParameter('severity', $severity);
        }

        if ($type !== '') {
            $qb->andWhere('a.alertType = :type')
                ->setParameter('type', $type);
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(a.eventType) LIKE :q OR LOWER(a.message) LIKE :q OR LOWER(a.ipAddress) LIKE :q OR LOWER(u.email) LIKE :q'
            )->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $alerts = $paginator->paginate($qb, $page, 15, [
            'defaultSortFieldName' => $this->mapAlertSortField($sort),
            'defaultSortDirection' => $direction,
            'sortFieldParameterName' => 'knp_sort',
            'sortDirectionParameterName' => 'knp_direction',
        ]);

        $openCount = (int) $alertRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.alertStatus = :status')
            ->setParameter('status', SecurityAlert::STATUS_EN_COURS)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('back/security_alert/index.html.twig', [
            'alerts' => $alerts,
            'status' => $status,
            'severity' => $severity,
            'type' => $type,
            'search' => $search,
            'sort' => $sort,
            'dir' => $direction,
            'open_count' => $openCount,
        ]);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(
        Request $request,
        SecurityAlertRepository $alertRepository,
        PaginatorInterface $paginator
    ): Response {
        $days = max(1, min(180, (int) $request->query->get('days', 30)));
        $type = $this->normalizeTypeFilter((string) $request->query->get('type', ''));
        $status = $this->normalizeHistoryStatusFilter((string) $request->query->get('status', 'all'));
        $search = trim((string) $request->query->get('q', ''));
        $sort = $this->normalizeHistorySort((string) $request->query->get('sort', 'changed_at'));
        $direction = $this->normalizeSortDirection((string) $request->query->get('dir', 'DESC'));
        $page = max(1, (int) $request->query->get('page', 1));
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        $qb = $alertRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC');

        if ($type !== '') {
            $qb->andWhere('a.alertType = :type')->setParameter('type', $type);
        }

        $historyRows = [];
        foreach ($qb->getQuery()->getResult() as $alert) {
            if (!$alert instanceof SecurityAlert) {
                continue;
            }

            $historyEntries = $alert->getStatusHistory();
            if ($historyEntries === []) {
                $historyEntries = [[
                    'status' => $alert->getAlertStatus(),
                    'changed_at' => $alert->getCreatedAt()->format('Y-m-d H:i:s'),
                    'source' => 'legacy',
                    'note' => 'Historique detaille indisponible, statut courant uniquement.',
                ]];
            }

            foreach ($historyEntries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $entryStatus = $this->normalizeStatusValue((string) ($entry['status'] ?? $alert->getAlertStatus()));
                if ($status !== 'all' && $entryStatus !== $status) {
                    continue;
                }

                $changedAt = $this->resolveHistoryDate(
                    $entry['changed_at'] ?? null,
                    $alert->getResolvedAt() ?? $alert->getCreatedAt()
                );

                $historyRows[] = [
                    'changed_at' => $changedAt,
                    'status' => $entryStatus,
                    'source' => (string) ($entry['source'] ?? 'system'),
                    'note' => (string) ($entry['note'] ?? ''),
                    'alert_id' => $alert->getId(),
                    'event_type' => $alert->getEventType(),
                    'alert_type' => $alert->getAlertType(),
                    'severity' => $alert->getSeverity(),
                    'risk_score' => $alert->getRiskScore(),
                    'user_email' => (string) ($alert->getUser()?->getEmail() ?? 'N/A'),
                    'ip_address' => (string) ($alert->getIpAddress() ?? 'N/A'),
                    'message' => $alert->getMessage(),
                ];
            }
        }

        usort(
            $historyRows,
            function (array $a, array $b) use ($sort, $direction): int {
                $left = $this->extractHistorySortValue($a, $sort);
                $right = $this->extractHistorySortValue($b, $sort);

                if ($left === $right) {
                    return 0;
                }

                $cmp = $left <=> $right;

                return $direction === 'ASC' ? $cmp : -$cmp;
            }
        );

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $historyRows = array_values(array_filter($historyRows, static function (array $row) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['status'] ?? ''),
                    (string) ($row['source'] ?? ''),
                    (string) ($row['note'] ?? ''),
                    (string) ($row['event_type'] ?? ''),
                    (string) ($row['alert_type'] ?? ''),
                    (string) ($row['severity'] ?? ''),
                    (string) ($row['user_email'] ?? ''),
                    (string) ($row['ip_address'] ?? ''),
                    (string) ($row['message'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        $historyPagination = $paginator->paginate($historyRows, $page, 20, [
            'sortFieldParameterName' => 'knp_sort',
            'sortDirectionParameterName' => 'knp_direction',
        ]);

        return $this->render('back/security_alert/history.html.twig', [
            'history_rows' => $historyPagination,
            'days' => $days,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'sort' => $sort,
            'dir' => $direction,
            'total_rows' => $historyPagination->getTotalItemCount(),
        ]);
    }

    #[Route('/{id}/status', name: 'set_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function setStatus(
        SecurityAlert $alert,
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityAlertRepository $alertRepository
    ): Response {
        if (!$this->isCsrfTokenValid('set_security_alert_status_' . $alert->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_security_alert_index');
        }

        $requestedStatus = (string) $request->request->get('alert_status', SecurityAlert::STATUS_TRAITEE);
        $targetStatus = $this->normalizeStatusAction($requestedStatus);
        if ($targetStatus === null) {
            $this->addFlash('error', 'Statut d alerte invalide.');

            return $this->redirectToRoute('admin_security_alert_index');
        }

        $actor = (string) ($this->getUser()?->getUserIdentifier() ?? 'admin');
        $updatedCount = 0;

        if ($targetStatus === SecurityAlert::STATUS_EN_COURS) {
            if ($alert->getAlertStatus() !== SecurityAlert::STATUS_EN_COURS) {
                $this->applyStatus($alert, SecurityAlert::STATUS_EN_COURS, $actor, 'Alerte reouverte.');
                $updatedCount = 1;
            }
        } else {
            $updatedCount = $this->closeOpenAlertsForCase(
                $alert,
                $targetStatus,
                $actor,
                $alertRepository
            );
        }

        if ($updatedCount > 0 && $targetStatus !== SecurityAlert::STATUS_EN_COURS && $alert->getUser() !== null) {
            $alert->getUser()?->setSecurityBlockedUntil(null);
            $alert->getUser()?->setUpdatedAt(new \DateTimeImmutable());
        }

        $entityManager->flush();

        if ($updatedCount > 0) {
            $this->addFlash('success', sprintf('%d alerte(s) mise(s) a jour (%s).', $updatedCount, $targetStatus));
        } else {
            $this->addFlash('info', 'Aucune alerte a mettre a jour pour ce cas.');
        }

        return $this->redirectToRoute('admin_security_alert_index', [
            'status' => $this->normalizeStatusFilter((string) $request->query->get('status', SecurityAlert::STATUS_EN_COURS)),
            'severity' => (string) $request->query->get('severity', ''),
            'type' => $this->normalizeTypeFilter((string) $request->query->get('type', '')),
            'q' => trim((string) $request->query->get('q', '')),
            'sort' => $this->normalizeAlertSort((string) $request->query->get('sort', 'createdAt')),
            'dir' => $this->normalizeSortDirection((string) $request->query->get('dir', 'DESC')),
            'page' => max(1, (int) $request->query->get('page', 1)),
        ]);
    }

    #[Route('/{id}/resolve', name: 'resolve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolve(
        SecurityAlert $alert,
        Request $request,
        EntityManagerInterface $entityManager,
        SecurityAlertRepository $alertRepository
    ): Response
    {
        if (!$this->isCsrfTokenValid('resolve_security_alert_' . $alert->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_security_alert_index');
        }

        $updatedCount = $this->closeOpenAlertsForCase(
            $alert,
            SecurityAlert::STATUS_TRAITEE,
            (string) ($this->getUser()?->getUserIdentifier() ?? 'admin'),
            $alertRepository
        );

        if ($updatedCount > 0 && $alert->getUser() !== null) {
            $alert->getUser()?->setSecurityBlockedUntil(null);
            $alert->getUser()?->setUpdatedAt(new \DateTimeImmutable());
        }

        $entityManager->flush();

        $this->addFlash(
            $updatedCount > 0 ? 'success' : 'info',
            $updatedCount > 0
                ? sprintf('%d alerte(s) marquee(s) comme traitee(s).', $updatedCount)
                : 'Aucune alerte ouverte a traiter pour ce cas.'
        );

        return $this->redirectToRoute('admin_security_alert_index', [
            'status' => $this->normalizeStatusFilter((string) $request->query->get('status', SecurityAlert::STATUS_EN_COURS)),
            'severity' => (string) $request->query->get('severity', ''),
            'type' => $this->normalizeTypeFilter((string) $request->query->get('type', '')),
            'q' => trim((string) $request->query->get('q', '')),
            'sort' => $this->normalizeAlertSort((string) $request->query->get('sort', 'createdAt')),
            'dir' => $this->normalizeSortDirection((string) $request->query->get('dir', 'DESC')),
            'page' => max(1, (int) $request->query->get('page', 1)),
        ]);
    }

    private function closeOpenAlertsForCase(
        SecurityAlert $alert,
        string $targetStatus,
        string $actor,
        SecurityAlertRepository $alertRepository
    ): int {
        $qb = $alertRepository->createQueryBuilder('a')
            ->andWhere('a.alertStatus = :statusOpen')
            ->andWhere('a.eventType = :eventType')
            ->setParameter('statusOpen', SecurityAlert::STATUS_EN_COURS)
            ->setParameter('eventType', $alert->getEventType());

        if ($alert->getUser() !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $alert->getUser());
        } elseif ($alert->getIpAddress() !== null && $alert->getIpAddress() !== '') {
            $qb->andWhere('a.ipAddress = :ip')->setParameter('ip', $alert->getIpAddress());
        } else {
            $qb->andWhere('a.id = :id')->setParameter('id', $alert->getId());
        }

        $updatedCount = 0;
        foreach ($qb->getQuery()->getResult() as $item) {
            if (!$item instanceof SecurityAlert) {
                continue;
            }

            $this->applyStatus($item, $targetStatus, $actor, 'Mise a jour manuelle depuis le back-office.');
            $updatedCount++;
        }

        return $updatedCount;
    }

    private function applyStatus(SecurityAlert $alert, string $status, string $actor, string $note): void
    {
        $alert->setAlertStatus($status);
        $alert->addStatusHistoryEntry($status, $actor, $note);
    }

    private function normalizeStatusFilter(string $status): string
    {
        $status = $this->normalizeStatusValue($status);
        $allowed = ['all', SecurityAlert::STATUS_EN_COURS, SecurityAlert::STATUS_TRAITEE, SecurityAlert::STATUS_IGNOREE];

        return in_array($status, $allowed, true) ? $status : SecurityAlert::STATUS_EN_COURS;
    }

    private function normalizeStatusAction(string $status): ?string
    {
        $status = $this->normalizeStatusValue($status);
        $allowed = [SecurityAlert::STATUS_EN_COURS, SecurityAlert::STATUS_TRAITEE, SecurityAlert::STATUS_IGNOREE];

        return in_array($status, $allowed, true) ? $status : null;
    }

    private function normalizeHistoryStatusFilter(string $status): string
    {
        $status = $this->normalizeStatusValue($status);
        $allowed = ['all', SecurityAlert::STATUS_EN_COURS, SecurityAlert::STATUS_TRAITEE, SecurityAlert::STATUS_IGNOREE];

        return in_array($status, $allowed, true) ? $status : 'all';
    }

    private function normalizeTypeFilter(string $type): string
    {
        $type = strtolower(trim($type));
        $allowed = [
            '',
            SecurityAlert::CATEGORY_MEDICALE,
            SecurityAlert::CATEGORY_COMPORTEMENTALE,
            SecurityAlert::CATEGORY_SECURITAIRE,
            SecurityAlert::CATEGORY_TECHNIQUE,
        ];

        return in_array($type, $allowed, true) ? $type : '';
    }

    private function normalizeStatusValue(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'open' => SecurityAlert::STATUS_EN_COURS,
            'resolved', 'resolue' => SecurityAlert::STATUS_TRAITEE,
            default => $status,
        };
    }

    private function resolveHistoryDate(mixed $raw, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        if ($raw instanceof \DateTimeImmutable) {
            return $raw;
        }

        if ($raw instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($raw);
        }

        if (is_string($raw) && trim($raw) !== '') {
            try {
                return new \DateTimeImmutable($raw);
            } catch (\Exception) {
            }
        }

        return $fallback;
    }

    private function normalizeAlertSort(string $sort): string
    {
        $allowed = ['createdAt', 'eventType', 'alertType', 'severity', 'riskScore', 'alertStatus', 'userEmail'];

        return in_array($sort, $allowed, true) ? $sort : 'createdAt';
    }

    private function mapAlertSortField(string $sort): string
    {
        return match ($sort) {
            'eventType' => 'a.eventType',
            'alertType' => 'a.alertType',
            'severity' => 'a.severity',
            'riskScore' => 'a.riskScore',
            'alertStatus' => 'a.alertStatus',
            'userEmail' => 'u.email',
            default => 'a.createdAt',
        };
    }

    private function normalizeHistorySort(string $sort): string
    {
        $allowed = ['changed_at', 'status', 'alert_type', 'severity', 'event_type', 'user_email', 'source'];

        return in_array($sort, $allowed, true) ? $sort : 'changed_at';
    }

    private function normalizeSortDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }

    private function extractHistorySortValue(array $row, string $sort): int|string
    {
        $value = $row[$sort] ?? null;
        if ($value instanceof \DateTimeInterface) {
            return (int) $value->format('U');
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return mb_strtolower((string) $value);
    }
}
