<?php

namespace App\Controller;

use App\Entity\Exercice;
use App\Form\ExerciceFormType;
use App\Repository\EvenementRepository;
use App\Repository\ExerciceRepository;
use App\Service\ExerciceAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COACH')]
class ExerciceController extends AbstractController
{
    #[Route('/coach/exercices/ai-description', name: 'exercice_ai_description', methods: ['POST'])]
    public function aiDescription(Request $request, ExerciceAiService $aiService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Requete invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $nom = trim((string) ($payload['nom'] ?? ''));
        if (strlen($nom) < 2) {
            return new JsonResponse(['error' => 'Le nom de l exercice est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $dureeRaw = $payload['duree'] ?? null;
        $duree = (is_numeric($dureeRaw) && (int) $dureeRaw > 0) ? (int) $dureeRaw : null;

        $repetitionsRaw = $payload['repetitions'] ?? null;
        $repetitions = (is_numeric($repetitionsRaw) && (int) $repetitionsRaw >= 0) ? (int) $repetitionsRaw : null;

        $evenement = trim((string) ($payload['evenement'] ?? ''));
        if ($evenement === '') {
            $evenement = null;
        }

        return new JsonResponse(
            $aiService->suggestDescription($nom, $duree, $repetitions, $evenement),
            Response::HTTP_OK
        );
    }

    #[Route('/coach/exercices/ai-improve-description', name: 'exercice_ai_improve_description', methods: ['POST'])]
    public function aiImproveDescription(Request $request, ExerciceAiService $aiService): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Requete invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) < 10) {
            return new JsonResponse(['error' => 'La description est trop courte pour etre amelioree.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $nom = trim((string) ($payload['nom'] ?? ''));

        return new JsonResponse(
            $aiService->improveDescription($description, $nom !== '' ? $nom : null),
            Response::HTTP_OK
        );
    }

    #[Route('/coach/exercices', name: 'exercice_index')]
    public function index(
        Request $request,
        ExerciceRepository $exerciceRepository,
        EvenementRepository $evenementRepository
    ): Response {
        $evenementId = $request->query->get('evenement');
        $evenementId = $evenementId !== null && $evenementId !== '' ? (int) $evenementId : null;
        $sort = $request->query->get('sort', 'nom');
        $order = $request->query->get('order', 'ASC');
        $q = $request->query->get('q');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 10;

        $result = $exerciceRepository->searchByCoachPaged(
            $this->getUser(),
            $evenementId,
            $q,
            $sort,
            $order,
            $page,
            $perPage
        );

        $evenements = $evenementRepository->findBy(
            ['coach' => $this->getUser()],
            ['dateDebut' => 'DESC']
        );

        return $this->render('exercice/index.html.twig', [
            'exercices' => $result['items'],
            'evenements' => $evenements,
            'selected_evenement' => $evenementId,
            'sort' => (string) $sort,
            'order' => (string) $order,
            'q' => (string) $q,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
        ]);
    }

    #[Route('/coach/exercices/new', name: 'exercice_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $exercice = new Exercice();

        $form = $this->createForm(ExerciceFormType::class, $exercice, [
            'coach' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($exercice);
            $em->flush();

            $this->addFlash('success', 'Exercice créé avec succès.');

            return $this->redirectToRoute('exercice_index');
        }

        return $this->render('exercice/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/coach/exercices/{id}', name: 'exercice_show', requirements: ['id' => '\d+'])]
    public function show(Exercice $exercice): Response
    {
        $this->assertCoach($exercice);

        return $this->render('exercice/show.html.twig', [
            'exercice' => $exercice,
        ]);
    }

    #[Route('/coach/exercices/{id}/edit', name: 'exercice_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Exercice $exercice, EntityManagerInterface $em): Response
    {
        $this->assertCoach($exercice);

        $form = $this->createForm(ExerciceFormType::class, $exercice, [
            'coach' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Exercice mis à jour.');

            return $this->redirectToRoute('exercice_index');
        }

        return $this->render('exercice/edit.html.twig', [
            'form' => $form->createView(),
            'exercice' => $exercice,
        ]);
    }

    #[Route('/coach/exercices/{id}/delete', name: 'exercice_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Exercice $exercice, EntityManagerInterface $em): Response
    {
        $this->assertCoach($exercice);

        if ($this->isCsrfTokenValid('delete_exercice_' . $exercice->getId(), (string) $request->request->get('_token'))) {
            $em->remove($exercice);
            $em->flush();

            $this->addFlash('success', 'Exercice supprimé.');
        }

        return $this->redirectToRoute('exercice_index');
    }

    private function assertCoach(Exercice $exercice): void
    {
        $evenement = $exercice->getEvenement();
        if (!$evenement || $evenement->getCoach() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
    }
}
