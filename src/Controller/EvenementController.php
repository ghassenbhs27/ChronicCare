<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementFormType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Service\EvenementAiService;
use App\Service\EvenementService;
use App\Service\HolidayService;
use App\Service\MapGeocodingService;
use App\Service\WeatherService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COACH')]
class EvenementController extends AbstractController
{
    #[Route('/coach/evenements/ai-description', name: 'evenement_ai_description', methods: ['POST'])]
    public function aiDescription(Request $request, EvenementAiService $aiService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Requete invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $titre = trim((string) ($payload['titre'] ?? ''));
        if (strlen($titre) < 3) {
            return new JsonResponse(['error' => 'Le titre de l evenement est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $lieu = trim((string) ($payload['lieu'] ?? ''));
        $dateDebut = trim((string) ($payload['dateDebut'] ?? ''));
        $dateFin = trim((string) ($payload['dateFin'] ?? ''));

        return new JsonResponse(
            $aiService->suggestDescription(
                $titre,
                $lieu !== '' ? $lieu : null,
                $dateDebut !== '' ? $dateDebut : null,
                $dateFin !== '' ? $dateFin : null
            ),
            Response::HTTP_OK
        );
    }

    #[Route('/coach/evenements/ai-improve-description', name: 'evenement_ai_improve_description', methods: ['POST'])]
    public function aiImproveDescription(Request $request, EvenementAiService $aiService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Requete invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) < 10) {
            return new JsonResponse(['error' => 'La description est trop courte pour etre amelioree.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $titre = trim((string) ($payload['titre'] ?? ''));

        return new JsonResponse(
            $aiService->improveDescription($description, $titre !== '' ? $titre : null),
            Response::HTTP_OK
        );
    }

    #[Route('/coach/evenements', name: 'evenement_index')]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        InscriptionEvenementRepository $inscriptionRepository,
        HolidayService $holidayService
    ): Response {
        $coach = $this->getAuthenticatedUser();
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'dateDebut');
        $order = $request->query->get('order', 'DESC');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $result = $evenementRepository->searchByCoachPaged(
            $coach,
            $q,
            $sort,
            $order,
            $page,
            $perPage
        );

        $evenements = $result['items'];
        $evenementIds = array_map(static fn (Evenement $evenement) => $evenement->getId(), $evenements);
        $inscriptionCounts = $inscriptionRepository->countByEvenementIds($evenementIds);
        $holidayMap = [];
        foreach ($evenements as $evenement) {
            $holiday = $holidayService->getHolidayName($evenement->getDateDebut());
            if ($holiday) {
                $holidayMap[$evenement->getId()] = $holiday;
            }
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenements,
            'q' => (string) $q,
            'sort' => (string) $sort,
            'order' => (string) $order,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
            'inscription_counts' => $inscriptionCounts,
            'holiday_map' => $holidayMap,
        ]);
    }

    #[Route('/coach/evenements/new', name: 'evenement_new')]
    public function new(Request $request, EvenementService $evenementService): Response
    {
        $evenement = new Evenement();
        $evenement->setCoach($this->getAuthenticatedUser());

        $form = $this->createForm(EvenementFormType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $evenementService->createEvenement($evenement, $this->getAuthenticatedUser());
                $this->addFlash('success', 'Evenement cree avec succes.');

                return $this->redirectToRoute('evenement_index');
            } catch (InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('evenement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/coach/evenements/{id}', name: 'evenement_show', requirements: ['id' => '\\d+'])]
    public function show(
        Evenement $evenement,
        InscriptionEvenementRepository $inscriptionRepository,
        HolidayService $holidayService,
        MapGeocodingService $mapGeocodingService,
        WeatherService $weatherService
    ): Response {
        $this->assertCoach($evenement);

        $exercices = $evenement->getExercices()->toArray();
        usort($exercices, static fn ($a, $b) => strcmp($a->getNom(), $b->getNom()));
        $inscriptions = $inscriptionRepository->findForEvenement($evenement);
        $holidayDebut = $holidayService->getHolidayName($evenement->getDateDebut());
        $holidayFin = $holidayService->getHolidayName($evenement->getDateFin());
        $mapLocation = $mapGeocodingService->resolveLocation($evenement->getLieu());
        $weather = $weatherService->getEventWeather($evenement->getDateDebut(), $evenement->getLieu());

        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
            'exercices' => $exercices,
            'inscriptions' => $inscriptions,
            'holiday_debut' => $holidayDebut,
            'holiday_fin' => $holidayFin,
            'map_location' => $mapLocation,
            'weather' => $weather,
        ]);
    }

    #[Route('/coach/evenements/{id}/edit', name: 'evenement_edit', requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Evenement $evenement, EvenementService $evenementService): Response
    {
        $this->assertCoach($evenement);

        $form = $this->createForm(EvenementFormType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $evenementService->updateEvenement($evenement);
                $this->addFlash('success', 'Evenement mis a jour.');

                return $this->redirectToRoute('evenement_index');
            } catch (InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('evenement/edit.html.twig', [
            'form' => $form->createView(),
            'evenement' => $evenement,
        ]);
    }

    #[Route('/coach/evenements/{id}/delete', name: 'evenement_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Evenement $evenement, EvenementService $evenementService): Response
    {
        $this->assertCoach($evenement);

        if ($this->isCsrfTokenValid('delete_evenement_' . $evenement->getId(), (string) $request->request->get('_token'))) {
            $evenementService->deleteEvenement($evenement);
            $this->addFlash('success', 'Evenement supprime.');
        }

        return $this->redirectToRoute('evenement_index');
    }

    #[Route('/coach/evenements/{id}/inscriptions.csv', name: 'evenement_inscriptions_csv', requirements: ['id' => '\\d+'])]
    public function exportInscriptionsCsv(
        Evenement $evenement,
        InscriptionEvenementRepository $inscriptionRepository
    ): Response {
        $this->assertCoach($evenement);

        $inscriptions = $inscriptionRepository->findForEvenement($evenement);

        $response = new StreamedResponse(function () use ($inscriptions, $evenement) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Prenom',
                'Nom',
                'Email',
                'Telephone',
                'Date inscription',
                'Evenement',
            ]);

            foreach ($inscriptions as $inscription) {
                fputcsv($handle, [
                    $inscription->getPrenom(),
                    $inscription->getNom(),
                    $inscription->getEmail(),
                    $inscription->getTelephone() ?? '',
                    $inscription->getCreatedAt()->format('d/m/Y H:i'),
                    $evenement->getTitre(),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="inscriptions-evenement-%d.csv"', $evenement->getId())
        );

        return $response;
    }

    private function assertCoach(Evenement $evenement): void
    {
        if ($evenement->getCoach() !== $this->getAuthenticatedUser()) {
            throw $this->createNotFoundException();
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
