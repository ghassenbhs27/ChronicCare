<?php

namespace App\Controller\Front;

use App\Entity\Activite;
use App\Entity\User;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\EtatRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activite')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ActiviteFrontController extends AbstractController
{
    #[Route('/', name: 'front_activite_index', methods: ['GET'])]
    public function index(Request $request, ActiviteRepository $repo, UserRepository $userRepository): Response
    {
        $currentUser = $this->getAuthenticatedUser();
        $isPro = $this->isProUser($currentUser);
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateActivite');
        $dir = $request->query->get('dir', 'DESC');

        $patients = [];
        $selectedPatient = $currentUser;
        if ($isPro) {
            $patients = $userRepository->findPatientsForFollowup();
            $selectedPatient = $this->resolveSelectedPatient($request, $userRepository, $patients);
        }

        $activites = [];
        $stats = [
            'totalActivites' => 0,
            'totalDuree' => 0,
            'totalCalories' => 0,
            'moyenneCalories' => 0,
            'moyenneDuree' => 0,
        ];
        if ($selectedPatient instanceof User) {
            $activites = $repo->findByUserWithSearchSort($selectedPatient, $q, $sort, $dir);
            $stats = $repo->getPersonalStats($selectedPatient);
        }

        $goalMinutes = 150;
        $goalCalories = 2000;
        $minutesProgress = min(100, (int) round(($stats['totalDuree'] / $goalMinutes) * 100));
        $caloriesProgress = min(100, (int) round(($stats['totalCalories'] / $goalCalories) * 100));

        return $this->render('front/activite/index.html.twig', [
            'activites' => $activites,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'stats' => $stats,
            'goalMinutes' => $goalMinutes,
            'goalCalories' => $goalCalories,
            'minutesProgress' => $minutesProgress,
            'caloriesProgress' => $caloriesProgress,
            'isPro' => $isPro,
            'patients' => $patients,
            'selectedPatient' => $selectedPatient,
        ]);
    }

    #[Route('/new', name: 'front_activite_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        EtatRepository $etatRepository
    ): Response {
        $currentUser = $this->getAuthenticatedUser();
        if (!$this->isProUser($currentUser)) {
            $this->addFlash('danger', 'Seuls le coach et le nutritionniste peuvent ajouter une activite.');
            return $this->redirectToRoute('front_activite_index');
        }

        $patients = $userRepository->findPatientsForFollowup();
        $selectedPatient = $this->resolveSelectedPatient($request, $userRepository, $patients);
        if (!$selectedPatient instanceof User) {
            $this->addFlash('warning', 'Aucun patient disponible pour ajouter une activite.');
            return $this->redirectToRoute('front_activite_index');
        }

        $activite = new Activite();
        $etats = $etatRepository->findBy(['utilisateur' => $selectedPatient], ['dateReleve' => 'DESC']);
        $form = $this->createForm(ActiviteType::class, $activite, ['etats' => $etats]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etat = $activite->getEtat();
            if ($etat && $etat->getUtilisateur()?->getId() !== $selectedPatient->getId()) {
                $form->addError(new FormError('L etat selectionne ne correspond pas au patient choisi.'));
            } else {
                $activite->setUtilisateur($selectedPatient);
                $this->applyCaloriesEstimation($activite);
                $em->persist($activite);
                $em->flush();
                $this->addFlash('success', sprintf(
                    'Activite enregistree avec succes pour %s %s.',
                    $selectedPatient->getPrenom(),
                    $selectedPatient->getNom()
                ));

                return $this->redirectToRoute('front_activite_index', ['patient' => $selectedPatient->getId()]);
            }
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Enregistrement impossible : verifiez les champs.');
        }

        return $this->render('front/activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
            'isPro' => true,
            'patients' => $patients,
            'selectedPatient' => $selectedPatient,
        ]);
    }

    #[Route('/{id}', name: 'front_activite_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        $currentUser = $this->getAuthenticatedUser();
        if (!$this->canAccessActivity($currentUser, $activite)) {
            throw $this->createAccessDeniedException('Acces refuse a cette activite.');
        }

        return $this->render('front/activite/show.html.twig', [
            'activite' => $activite,
            'isPro' => $this->isProUser($currentUser),
        ]);
    }

    #[Route('/{id}/edit', name: 'front_activite_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Activite $activite,
        EntityManagerInterface $em,
        EtatRepository $etatRepository
    ): Response {
        $currentUser = $this->getAuthenticatedUser();
        if (!$this->isProUser($currentUser)) {
            throw $this->createAccessDeniedException('Le patient ne peut pas modifier une activite.');
        }
        if (!$this->canAccessActivity($currentUser, $activite)) {
            throw $this->createAccessDeniedException('Acces refuse a cette activite.');
        }

        $patient = $activite->getUtilisateur();
        $etats = $patient ? $etatRepository->findBy(['utilisateur' => $patient], ['dateReleve' => 'DESC']) : [];
        $form = $this->createForm(ActiviteType::class, $activite, ['etats' => $etats]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etat = $activite->getEtat();
            if ($etat && $patient && $etat->getUtilisateur()?->getId() !== $patient->getId()) {
                $form->addError(new FormError('L etat selectionne ne correspond pas au patient de cette activite.'));
            } else {
                $this->applyCaloriesEstimation($activite);
                $em->flush();
                $this->addFlash('success', 'Activite mise a jour avec succes.');

                return $this->redirectToRoute('front_activite_index', ['patient' => $patient?->getId()]);
            }
        }
        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Modification impossible : verifiez les champs.');
        }

        return $this->render('front/activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form->createView(),
            'isPro' => true,
        ]);
    }

    #[Route('/{id}', name: 'front_activite_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $em): Response
    {
        $currentUser = $this->getAuthenticatedUser();
        if (!$this->isProUser($currentUser)) {
            throw $this->createAccessDeniedException('Le patient ne peut pas supprimer une activite.');
        }
        if (!$this->canAccessActivity($currentUser, $activite)) {
            throw $this->createAccessDeniedException('Acces refuse a cette activite.');
        }

        $patientId = $activite->getUtilisateur()?->getId();
        if ($this->isCsrfTokenValid('delete' . $activite->getId(), $request->request->get('_token'))) {
            $em->remove($activite);
            $em->flush();
            $this->addFlash('success', 'Activite supprimee avec succes.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide : suppression refusee.');
        }

        return $this->redirectToRoute('front_activite_index', ['patient' => $patientId]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }

        return $user;
    }

    private function isProUser(User $user): bool
    {
        $roles = $user->getRoles();
        return in_array('ROLE_COACH', $roles, true) || in_array('ROLE_NUTRITIONNISTE', $roles, true);
    }

    /**
     * @param User[] $patients
     */
    private function resolveSelectedPatient(Request $request, UserRepository $userRepository, array $patients): ?User
    {
        $patientId = (int) ($request->query->get('patient') ?? $request->request->get('patient'));
        if ($patientId > 0) {
            $patient = $userRepository->find($patientId);
            if ($patient instanceof User) {
                foreach ($patients as $candidate) {
                    if ($candidate->getId() === $patient->getId()) {
                        return $patient;
                    }
                }
            }
        }

        return $patients[0] ?? null;
    }

    private function canAccessActivity(User $user, Activite $activite): bool
    {
        $ownerId = $activite->getUtilisateur()?->getId();
        if ($ownerId === null) {
            return false;
        }
        if ($this->isProUser($user)) {
            return true;
        }

        return $ownerId === $user->getId();
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
