<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/appointments')]
#[IsGranted('ROLE_ADMIN')]
class AppointmentController extends AbstractController
{
    #[Route('/', name: 'admin_appointment_index')]
    public function index(): Response
    {
        return $this->render('back/appointment/index.html.twig');
    }
}
