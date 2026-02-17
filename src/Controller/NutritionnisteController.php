<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nutritionniste')]
#[IsGranted('ROLE_NUTRITIONNISTE')]
class NutritionnisteController extends AbstractController
{
    #[Route('', name: 'nutritionniste_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('nutritionniste/dashboard.html.twig');
    }

    #[Route('/rdvs', name: 'nutritionniste_rdvs', methods: ['GET'])]
    public function rdvs(): Response
    {
        return $this->redirectToRoute('pro_rdv_list');
    }

    #[Route('/publications', name: 'nutritionniste_publications', methods: ['GET'])]
    public function publications(): Response
    {
        return $this->redirectToRoute('publication_index');
    }

    #[Route('/evenements', name: 'nutritionniste_evenements', methods: ['GET'])]
    public function evenements(): Response
    {
        return $this->redirectToRoute('evenement_index');
    }

    #[Route('/exercices', name: 'nutritionniste_exercices', methods: ['GET'])]
    public function exercices(): Response
    {
        return $this->redirectToRoute('exercice_index');
    }
}

