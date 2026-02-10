<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/statistics')]
#[IsGranted('ROLE_ADMIN')]
class StatisticsController extends AbstractController
{
    #[Route('/', name: 'admin_statistics_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $since30 = new \DateTimeImmutable('-30 days');

        $totalUsers = $userRepository->countAllUsers();
        $newUsers30 = $userRepository->countUsersSince($since30);

        $byDay = $userRepository->registrationsByDay($since30); // day,total
        $byGenre = $userRepository->countByGenre();             // genre,total
        $byRole = $userRepository->countByMainRole();           // role,total

        // Préparer data pour Chart.js
        $labelsDay = array_map(function ($r) {
            $y = (int) $r['y'];
            $m = str_pad((string) (int) $r['m'], 2, '0', STR_PAD_LEFT);
            $d = str_pad((string) (int) $r['d'], 2, '0', STR_PAD_LEFT);
            return "$y-$m-$d";
        }, $byDay);

        $dataDay = array_map(fn($r) => (int) $r['total'], $byDay);

        $labelsGenre = array_map(fn($r) => $r['genre'] ?? 'N/A', $byGenre);
        $dataGenre = array_map(fn($r) => (int) $r['total'], $byGenre);

        // jolies étiquettes
        $roleLabelsMap = [
            'ROLE_PATIENT' => 'Patients',
            'ROLE_COACH' => 'Coachs',
            'ROLE_NUTRITIONIST' => 'Nutritionnistes',
            'ROLE_ADMIN' => 'Admins',
            'OTHER' => 'Autres',
        ];
        $labelsRole = array_map(fn($r) => $roleLabelsMap[$r['role']] ?? $r['role'], $byRole);
        $dataRole = array_map(fn($r) => (int) $r['total'], $byRole);

        return $this->render('back/statistics/index.html.twig', [
            'totalUsers' => $totalUsers,
            'newUsers30' => $newUsers30,

            'labelsDay' => $labelsDay,
            'dataDay' => $dataDay,

            'labelsGenre' => $labelsGenre,
            'dataGenre' => $dataGenre,

            'labelsRole' => $labelsRole,
            'dataRole' => $dataRole,
        ]);
    }
}
