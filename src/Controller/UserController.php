<?php
namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityBadgeService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/assign-most-active-badge', name: 'app_user_assign_most_active_badge', methods: ['POST'])]
    public function assignMostActiveBadge(Request $request, ActivityBadgeService $activityBadgeService): Response
    {
        if (!$this->isCsrfTokenValid('assign_most_active_badge', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $days = max(1, (int) $request->request->get('days', 30));
        $result = $activityBadgeService->assignMostActiveBadge($days);
        /** @var User|null $winner */
        $winner = $result['winner'] ?? null;
        $score = (int) ($result['score'] ?? 0);

        if (!$winner || $score <= 0) {
            $this->addFlash('warning', 'Aucun utilisateur actif trouve pour attribuer le badge.');
            return $this->redirectToRoute('app_user_index');
        }

        $this->addFlash(
            'success',
            sprintf(
                'Badge attribue a %s (%s) avec score %d. Recompense: %s',
                $winner->getFullName(),
                (string) $winner->getEmail(),
                $score,
                (string) $winner->getActivityReward()
            )
        );

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/assign-badge', name: 'app_user_assign_badge_to_user', methods: ['POST'])]
    public function assignBadgeToUser(Request $request, User $user, ActivityBadgeService $activityBadgeService): Response
    {
        if (!$this->isCsrfTokenValid('assign_badge_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $roles = $user->getRoles();
        $isAllowedRole = in_array('ROLE_PATIENT', $roles, true)
            || in_array('ROLE_COACH', $roles, true)
            || in_array('ROLE_NUTRITIONNISTE', $roles, true);

        if (!$isAllowedRole) {
            $this->addFlash('warning', 'Le badge manuel est reserve aux patients, coachs et nutritionnistes.');
            return $this->redirectToRoute('app_user_index');
        }

        $activityBadgeService->assignBadgeToUser($user);

        $this->addFlash(
            'success',
            sprintf(
                'Badge attribue manuellement a %s (%s). Recompense: %s',
                $user->getFullName(),
                (string) $user->getEmail(),
                (string) $user->getActivityReward()
            )
        );

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $user->setIsActive(true);

        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('accountType')->getData();
            $user->setRoles(array_unique(['ROLE_USER', $role]));
            $user->setRequestedRole($role);
            $user->setApprovalStatus(User::APPROVAL_APPROVED);
            $user->setIsActive(true);

            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', '🎉 Utilisateur créé avec succès !');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('back/users/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, PaginatorInterface $paginator): Response
    {
        $sort = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $search = $request->query->get('q');
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));

        $now = new \DateTimeImmutable();
        $prevMonth = $now->modify('-1 month')->setTime(0, 0, 0);
        $totalUsers = $userRepository->countAllUsers();
        $newThisMonth = $userRepository->countUsersSince($prevMonth);
        $activeUsers = $userRepository->countActiveUsers(30);
        $activeRate = $totalUsers > 0 ? (int) round($activeUsers / $totalUsers * 100) : 0;
        $prevMonthTotal = max(1, $totalUsers - $newThisMonth);
        $totalPercent = $prevMonthTotal > 0 ? round($newThisMonth / $prevMonthTotal * 100, 1) : 0;

        $since7 = $now->modify('-7 days');
        $byDay = $userRepository->registrationsByDay($since7);
        $dayMap = [];
        foreach ($byDay as $row) {
            $key = sprintf('%04d-%02d-%02d', $row['y'], $row['m'], $row['d']);
            $dayMap[$key] = (int) $row['total'];
        }
        $trendLabels = [];
        $trendData = [];
        $dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $trendLabels[] = $dayNames[(int) $d->format('N') - 1];
            $trendData[] = $dayMap[$key] ?? 0;
        }

        $byRole = $userRepository->countByMainRole();
        $roleLabels = [
            'ROLE_ADMIN' => 'Admin',
            'ROLE_COACH' => 'Coach',
            'ROLE_NUTRITIONNISTE' => 'Nutritionniste',
            'ROLE_PATIENT' => 'Patient',
            'OTHER' => 'Autre',
        ];
        $distributionByRole = [];
        foreach ($byRole as $row) {
            $distributionByRole[] = [
                'label' => $roleLabels[$row['role']] ?? $row['role'],
                'total' => $row['total'],
            ];
        }

        $qb = $userRepository->createQueryBuilderForManagement($search, $roleFilter ?: null, $sort, $order, $statusFilter ?: null);
        // Options pour éviter que Knp Sortable n'écrase notre ORDER BY (conflit alias/champ)
        $pagination = $paginator->paginate($qb, $page, 10, [
            'sortFieldParameterName' => 'list_sort',
            'sortDirectionParameterName' => 'list_dir',
        ]);

        $vars = [
            'users' => $pagination,
            'currentSort' => $sort,
            'currentOrder' => $order,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('back/users/_list.html.twig', $vars);
        }

        $usersWithoutRoleCount = $userRepository->countUsersWithoutRole();

        return $this->render('back/users/index.html.twig', array_merge($vars, [
            'total_users' => $totalUsers,
            'new_this_month' => $newThisMonth,
            'active_users' => $activeUsers,
            'active_rate' => $activeRate,
            'total_percent' => $totalPercent,
            'trend_labels' => $trendLabels,
            'trend_data' => $trendData,
            'distribution_by_role' => $distributionByRole,
            'users_without_role_count' => $usersWithoutRoleCount,
        ]));
    }
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $mainRole = 'ROLE_PATIENT'; // Valeur par défaut si le compte n'a pas encore de rôle
        foreach ($user->getRoles() as $r) {
            if ($r !== 'ROLE_USER') {
                $mainRole = $r;
                break;
            }
        }
        $form->get('role')->setData($mainRole);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();
            $user->setRoles($role ? array_unique([$role]) : []);
            $user->setRequestedRole($role ?: $user->getRequestedRole());
            if ($user->isActive()) {
                $user->setApprovalStatus(User::APPROVAL_APPROVED);
                $user->setSecurityBlockedUntil(null);
            }

            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', '✏️ Utilisateur modifié avec succès !');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('back/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/details', name: 'app_user_details', methods: ['GET'])]
    public function details(User $user): Response
    {
        return $this->render('back/users/details.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/approve', name: 'app_user_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('approve' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $role = $user->getRequestedRole() ?: 'ROLE_PATIENT';
        $user->setRoles([$role]);
        $user->setIsActive(true);
        $user->setApprovalStatus(User::APPROVAL_APPROVED);
        $user->setSecurityBlockedUntil(null);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $notificationService->sendUserApprovalDecision($user, true);
        $this->addFlash('success', 'Compte approuve avec succes et debloque.');

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/unblock', name: 'app_user_unblock', methods: ['POST'])]
    public function unblock(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('unblock' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setSecurityBlockedUntil(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Compte debloque avec succes.');

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/reject', name: 'app_user_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('reject' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setRoles([]);
        $user->setIsActive(false);
        $user->setApprovalStatus(User::APPROVAL_REJECTED);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $notificationService->sendUserApprovalDecision($user, false);
        $this->addFlash('warning', 'Compte refuse.');

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', "🗑️ Utilisateur supprimé !");
        }

        return $this->redirectToRoute('app_user_index');
    }
    #[Route('/export/pdf', name: 'app_user_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, UserRepository $userRepository): Response
    {
    // ✅ récupérer mêmes filtres/tri que la liste
        $sort = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $search = trim((string) $request->query->get('q', ''));
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');

        $users = $userRepository->findAllSortedAndFiltered($search, $sort, $order, $roleFilter ?: null, $statusFilter ?: null);

    // ✅ HTML depuis Twig
        $html = $this->renderView('back/users/pdf.html.twig', [
            'users' => $users,
            'generatedAt' => new \DateTimeImmutable(),
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

    // ✅ Dompdf config
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // support accents
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'utilisateurs_' . (new \DateTimeImmutable())->format('Y-m-d_H-i') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                 'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    
}
