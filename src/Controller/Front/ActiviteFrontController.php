<?php

namespace App\Controller\Front;

use App\Entity\Activite;
use App\Entity\User;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/activite')]
class ActiviteFrontController extends AbstractController
{
    #[Route('/', name: 'front_activite_index', methods: ['GET'])]
    public function index(
        Request $request,
        ActiviteRepository $repo,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $this->resolvePatient($userRepository, $em);
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateActivite');
        $dir = $request->query->get('dir', 'DESC');

        $activites = $repo->findByUserWithSearchSort($user, $q, $sort, $dir);
        $stats = $repo->getPersonalStats($user);

        $goalMinutes = 150;
        $goalCalories = 2000;
        $minutesProgress = $goalMinutes > 0 ? min(100, (int) round(($stats['totalDuree'] / $goalMinutes) * 100)) : 0;
        $caloriesProgress = $goalCalories > 0 ? min(100, (int) round(($stats['totalCalories'] / $goalCalories) * 100)) : 0;

        return $this->render(
            'front/activite/index.html.twig',
            compact('activites', 'q', 'sort', 'dir', 'stats', 'goalMinutes', 'goalCalories', 'minutesProgress', 'caloriesProgress')
        );
    }

    #[Route('/new', name: 'front_activite_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        $patient = $this->resolvePatient($userRepository, $em);
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activite->setUtilisateur($patient);
            $this->applyCaloriesEstimation($activite);
            $em->persist($activite);
            $em->flush();
            $this->addFlash('success', 'Activité enregistrée avec succès.');

            return $this->redirectToRoute('front_activite_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Enregistrement impossible : vérifiez les champs.');
        }

        return $this->render('front/activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'front_activite_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Activite $activite, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $patient = $this->resolvePatient($userRepository, $em);
        if ($activite->getUtilisateur()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException('Cette activité ne vous appartient pas.');
        }

        return $this->render('front/activite/show.html.twig', compact('activite'));
    }

    #[Route('/{id}/edit', name: 'front_activite_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Activite $activite,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        $patient = $this->resolvePatient($userRepository, $em);
        if ($activite->getUtilisateur()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException('Cette activité ne vous appartient pas.');
        }

        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyCaloriesEstimation($activite);
            $em->flush();
            $this->addFlash('success', 'Activité mise à jour avec succès.');

            return $this->redirectToRoute('front_activite_index');
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Modification impossible : vérifiez les champs.');
        }

        return $this->render('front/activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'front_activite_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        Request $request,
        Activite $activite,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        $patient = $this->resolvePatient($userRepository, $em);
        if ($activite->getUtilisateur()?->getId() !== $patient->getId()) {
            throw $this->createAccessDeniedException('Cette activité ne vous appartient pas.');
        }

        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->request->get('_token'))) {
            $em->remove($activite);
            $em->flush();
            $this->addFlash('success', 'Activité supprimée avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide : suppression refusée.');
        }

        return $this->redirectToRoute('front_activite_index');
    }

    private function resolvePatient(UserRepository $userRepository, EntityManagerInterface $em): User
    {
        $current = $this->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $fallback = $userRepository->findOneBy([], ['id' => 'ASC']);
        if ($fallback instanceof User) {
            return $fallback;
        }

        $stamp = date('YmdHis');
        $user = new User();
        $user->setNom('Patient');
        $user->setPrenom('Temporaire');
        $user->setEmail('patient-activite-' . $stamp . '@local.chroniccare');
        $user->setPassword('temporary-password');
        $user->setRoles(['ROLE_PATIENT']);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function applyCaloriesEstimation(Activite $activite): void
    {
        $calories = (int) ($activite->getCalories() ?? 0);
        if ($calories > 0) {
            return;
        }

        $type = mb_strtolower((string) $activite->getType());
        $duree = (int) ($activite->getDuree() ?? 0);
        $burnPerMinute = match (true) {
            str_contains($type, 'course') => 10,
            str_contains($type, 'velo') => 8,
            str_contains($type, 'marche') => 5,
            str_contains($type, 'natation') => 9,
            default => 6,
        };

        $activite->setCalories(max(0, $duree * $burnPerMinute));
    }
}
