<?php

namespace App\Controller\Admin;

use App\Entity\SecurityAlert;
use App\Repository\SecurityAlertRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function index(Request $request, SecurityAlertRepository $alertRepository): Response
    {
        $status = (string) $request->query->get('status', 'open');
        $severity = (string) $request->query->get('severity', '');

        $qb = $alertRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')->addSelect('u')
            ->orderBy('a.createdAt', 'DESC');

        if ($status === 'open') {
            $qb->andWhere('a.isResolved = false');
        } elseif ($status === 'resolved') {
            $qb->andWhere('a.isResolved = true');
        }

        if ($severity !== '') {
            $qb->andWhere('a.severity = :severity')
                ->setParameter('severity', $severity);
        }

        $alerts = $qb->setMaxResults(200)->getQuery()->getResult();

        $openCount = (int) $alertRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isResolved = false')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('back/security_alert/index.html.twig', [
            'alerts' => $alerts,
            'status' => $status,
            'severity' => $severity,
            'open_count' => $openCount,
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

        $now = new \DateTimeImmutable();
        $resolvedCount = 0;

        $qb = $alertRepository->createQueryBuilder('a')
            ->andWhere('a.isResolved = false')
            ->andWhere('a.eventType = :eventType')
            ->setParameter('eventType', $alert->getEventType());

        if ($alert->getUser() !== null) {
            $qb->andWhere('a.user = :user')->setParameter('user', $alert->getUser());
        } elseif ($alert->getIpAddress() !== null && $alert->getIpAddress() !== '') {
            $qb->andWhere('a.ipAddress = :ip')->setParameter('ip', $alert->getIpAddress());
        } else {
            $qb->andWhere('a.id = :id')->setParameter('id', $alert->getId());
        }

        $alertsToResolve = $qb->getQuery()->getResult();
        foreach ($alertsToResolve as $item) {
            if (!$item instanceof SecurityAlert || $item->isResolved()) {
                continue;
            }

            $item->setIsResolved(true);
            $item->setResolvedAt($now);
            $resolvedCount++;
        }

        $user = $alert->getUser();
        if ($user !== null) {
            $user->setSecurityBlockedUntil(null);
            $user->setUpdatedAt($now);
        }

        $entityManager->flush();

        if ($resolvedCount > 0) {
            $this->addFlash('success', sprintf('%d alerte(s) marquee(s) comme resolue(s).', $resolvedCount));
        } else {
            $this->addFlash('info', 'Aucune alerte ouverte a resoudre pour ce cas.');
        }

        return $this->redirectToRoute('admin_security_alert_index', [
            'status' => (string) $request->query->get('status', 'open'),
            'severity' => (string) $request->query->get('severity', ''),
        ]);
    }
}
