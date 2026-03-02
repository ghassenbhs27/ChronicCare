<?php

namespace App\Controller\Admin;

use App\Entity\MedicalDossierAuditLog;
use App\Repository\MedicalDossierAuditLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/medical-audits', name: 'admin_medical_audit_')]
#[IsGranted('ROLE_ADMIN')]
class MedicalAuditController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        MedicalDossierAuditLogRepository $auditLogRepository,
        PaginatorInterface $paginator
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $action = $this->normalizeActionFilter((string) $request->query->get('action', ''));
        $actorRole = $this->normalizeRoleFilter((string) $request->query->get('actor_role', ''));
        $patientRole = $this->normalizeRoleFilter((string) $request->query->get('patient_role', ''));
        $legacyRole = $this->normalizeRoleFilter((string) $request->query->get('role', ''));
        $legacyRoleTarget = $this->normalizeRoleTarget((string) $request->query->get('role_target', 'actor'));

        if ($legacyRole !== '') {
            if ($legacyRoleTarget === 'patient' && $patientRole === '') {
                $patientRole = $legacyRole;
            } elseif ($legacyRoleTarget === 'all') {
                if ($actorRole === '') {
                    $actorRole = $legacyRole;
                }
                if ($patientRole === '') {
                    $patientRole = $legacyRole;
                }
            } elseif ($actorRole === '') {
                $actorRole = $legacyRole;
            }
        }
        $sort = $this->normalizeSort((string) $request->query->get('sort', 'createdAt'));
        $direction = $this->normalizeDirection((string) $request->query->get('dir', 'DESC'));
        $page = max(1, (int) $request->query->get('page', 1));

        $qb = $auditLogRepository->createQueryBuilder('l')
            ->leftJoin('l.actor', 'a')->addSelect('a')
            ->leftJoin('l.dossier', 'd')->addSelect('d')
            ->leftJoin('d.user', 'p')->addSelect('p')
            ->orderBy($this->mapSortField($sort), $direction)
            ->addOrderBy('l.id', 'DESC');

        if ($action !== '') {
            $qb->andWhere('l.action = :action')->setParameter('action', $action);
        }

        if ($actorRole !== '') {
            $qb->andWhere('(l.actorRole = :actorRole OR a.roles LIKE :actorRoleLike)')
                ->setParameter('actorRole', $actorRole)
                ->setParameter('actorRoleLike', '%' . $actorRole . '%');
        }

        if ($patientRole !== '') {
            $qb->andWhere('p.roles LIKE :patientRoleLike')
                ->setParameter('patientRoleLike', '%' . $patientRole . '%');
        }

        if ($search !== '') {
            $qb->andWhere(
                'LOWER(l.action) LIKE :q
                OR LOWER(COALESCE(l.actorEmail, \'\')) LIKE :q
                OR LOWER(COALESCE(l.actorRole, \'\')) LIKE :q
                OR LOWER(COALESCE(l.targetDisease, \'\')) LIKE :q
                OR LOWER(COALESCE(l.ipAddress, \'\')) LIKE :q
                OR LOWER(COALESCE(p.email, \'\')) LIKE :q
                OR LOWER(COALESCE(p.nom, \'\')) LIKE :q
                OR LOWER(COALESCE(p.prenom, \'\')) LIKE :q'
            )->setParameter('q', '%' . mb_strtolower($search) . '%');
        }

        $logs = $paginator->paginate($qb, $page, 5, [
            'sortFieldParameterName' => 'knp_sort',
            'sortDirectionParameterName' => 'knp_direction',
        ]);

        return $this->render('back/medical_audit/index.html.twig', [
            'logs' => $logs,
            'search' => $search,
            'action' => $action,
            'actor_role' => $actorRole,
            'patient_role' => $patientRole,
            'sort' => $sort,
            'dir' => $direction,
        ]);
    }

    private function normalizeActionFilter(string $action): string
    {
        $action = strtolower(trim($action));
        $allowed = [
            '',
            MedicalDossierAuditLog::ACTION_CONSULTATION,
            MedicalDossierAuditLog::ACTION_CREATION,
            MedicalDossierAuditLog::ACTION_MODIFICATION,
            MedicalDossierAuditLog::ACTION_AJOUT_MALADIE,
        ];

        return in_array($action, $allowed, true) ? $action : '';
    }

    private function normalizeRoleFilter(string $role): string
    {
        $role = strtoupper(trim($role));
        $allowed = ['', 'ROLE_ADMIN', 'ROLE_PATIENT', 'ROLE_COACH', 'ROLE_NUTRITIONNISTE', 'ROLE_USER'];

        return in_array($role, $allowed, true) ? $role : '';
    }

    private function normalizeRoleTarget(string $roleTarget): string
    {
        $roleTarget = strtolower(trim($roleTarget));
        $allowed = ['actor', 'patient', 'all'];

        return in_array($roleTarget, $allowed, true) ? $roleTarget : 'actor';
    }

    private function normalizeSort(string $sort): string
    {
        $allowed = ['createdAt', 'action', 'actorEmail', 'actorRole', 'targetDisease', 'patientEmail'];

        return in_array($sort, $allowed, true) ? $sort : 'createdAt';
    }

    private function normalizeDirection(string $direction): string
    {
        return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }

    private function mapSortField(string $sort): string
    {
        return match ($sort) {
            'action' => 'l.action',
            'actorEmail' => 'l.actorEmail',
            'actorRole' => 'l.actorRole',
            'targetDisease' => 'l.targetDisease',
            'patientEmail' => 'p.email',
            default => 'l.createdAt',
        };
    }
}
