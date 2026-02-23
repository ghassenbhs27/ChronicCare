<?php

namespace App\Controller;

use App\Form\SearchProfessionnelFormType;
use App\Repository\RDVRepository;
use App\Repository\UserRepository;
use App\Service\CalendarService;
use App\Service\HolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/professionnels')]
final class ProfessionnelController extends AbstractController
{
    #[Route('', name: 'professionnels_list', methods: ['GET'])]
    public function list(Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(SearchProfessionnelFormType::class);
        $form->handleRequest($request);

        $data = $form->getData() ?? [];
        $professionnels = $userRepository->findProfessionnels($data['q'] ?? null);

        return $this->render('professionnel/list.html.twig', [
            'form' => $form->createView(),
            'professionnels' => $professionnels,
        ]);
    }

    #[Route('/{id}', name: 'professionnels_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        UserRepository $userRepository,
        RDVRepository $rdvRepository,
        CalendarService $calendarService,
        HolidayService $holidayService
    ): Response
    {
        $professionnel = $userRepository->find($id);
        if (
            !$professionnel
            || (!in_array('ROLE_COACH', $professionnel->getRoles(), true)
                && !in_array('ROLE_NUTRITIONNISTE', $professionnel->getRoles(), true))
        ) {
            throw $this->createNotFoundException();
        }

        $start = new \DateTimeImmutable('today');
        $end = $start->modify('+30 days');
        $holidayDates = $holidayService->getHolidayDatesInRange($start, $end);
        $reservedSlots = $rdvRepository->findReservedSlotsForProfessionnel($professionnel->getId(), $start, $end);
        $reservedDates = array_values(array_unique(array_map(static function ($d): string {
            if ($d instanceof \DateTimeInterface) {
                return $d->format('Y-m-d');
            }
            return (new \DateTimeImmutable((string) $d))->format('Y-m-d');
        }, $reservedSlots)));

        return $this->render('professionnel/show.html.twig', [
            'professionnel' => $professionnel,
            'holidayDates' => $holidayDates,
            'reservedDates' => $reservedDates,
        ]);
    }
}
