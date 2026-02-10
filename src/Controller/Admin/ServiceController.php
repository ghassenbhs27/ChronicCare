<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'admin_service_index')]
    public function index(): Response
    {
        return $this->render('back/service/index.html.twig');
    }
}
