<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\PatientSegmentationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/patient-segments', name: 'admin_patient_segment_')]
#[IsGranted('ROLE_ADMIN')]
class PatientSegmentController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PatientSegmentationService $patientSegmentationService): Response
    {
        $days = max(7, min(120, (int) $request->query->get('days', 30)));
        $data = $patientSegmentationService->buildSegments($days);

        return $this->render('back/patient_segment/index.html.twig', [
            'days' => $days,
            'segments' => $data,
        ]);
    }
}
