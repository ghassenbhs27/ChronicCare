<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Entity\User;
use App\Form\InscriptionEvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Service\HolidayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class FrontController extends AbstractController
{
    #[Route('/forum', name: 'forum')]
    public function forum(): RedirectResponse
    {
        return $this->redirectToRoute('publication_index');
    }
    #[Route('/', name: 'home')]
    #[Route('/', name: 'index')]
    #[Route('/contenu-educatif', name: 'contenu-educatif')]
    public function index(): Response|RedirectResponse
    {
        if ($this->getUser() && $this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('suivi_front_dashboard');
        }
        return $this->render('front/index.html.twig');
    }

    #[Route('/coachs', name: 'coachs')]
    #[Route('/appointment', name: 'appointment')]
    #[Route('/services', name: 'services')]
    #[Route('/rendez-vous', name: 'rendez-vous')]
    public function coachs(): Response
    {
        return $this->render('front/coachs.html.twig');
    }

    #[Route('/connexion', name: 'connexion')]
    public function connexion(): Response
    {
        return $this->render('front/connexion.html.twig');
    }

    #[Route('/inscription', name: 'inscription')]
    public function inscription(): Response
    {
        return $this->redirectToRoute('app_register');
    }

    #[Route('/terms', name: 'terms')]
    public function terms(): Response
    {
        return $this->redirectToRoute('privacy');
    }

    #[Route('/boutique', name: 'boutique')]
    public function boutique(\App\Repository\ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findAllActive();
        $categories = $produitRepository->findAllCategories();

        return $this->render('front/boutique.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
        ]);
    }

    #[Route('/service-details', name: 'service-details')]
    public function serviceDetails(): Response
    {
        return $this->render('front/boutique.html.twig');
    }

    #[Route('/faq', name: 'faq')]
    public function faq(): Response
    {
        return $this->render('front/faq.html.twig');
    }

    #[Route('/nutritionnistes', name: 'nutritionnistes')]
    public function nutritionnistes(): Response
    {
        return $this->render('front/nutritionnistes.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('/privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('front/privacy.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }

    #[Route('/evenements', name: 'front_evenements')]
    public function evenements(EvenementRepository $evenementRepository, HolidayService $holidayService): Response
    {
        $evenements = $evenementRepository->findValidForPublic();
        $holidayMap = [];
        foreach ($evenements as $evenement) {
            $holiday = $holidayService->getHolidayName($evenement->getDateDebut());
            if ($holiday) {
                $holidayMap[$evenement->getId()] = $holiday;
            }
        }

        return $this->render('front/evenements.html.twig', [
            'evenements' => $evenements,
            'holiday_map' => $holidayMap,
        ]);
    }

    #[Route('/evenements/{id}', name: 'front_evenement_show', requirements: ['id' => '\d+'])]
    public function evenementShow(
        Request $request,
        Evenement $evenement,
        EntityManagerInterface $em,
        HolidayService $holidayService,
        InscriptionEvenementRepository $inscriptionRepo
    ): Response {
        if (!$evenement->isValide()) {
            throw $this->createNotFoundException('Événement non disponible.');
        }

        $user = $this->getUser();
        $alreadyInscrit = false;
        if ($user instanceof User && $user->getEmail()) {
            $alreadyInscrit = $inscriptionRepo->findOneByEvenementAndEmail($evenement, $user->getEmail()) !== null;
        }

        $inscription = new InscriptionEvenement();
        $inscription->setEvenement($evenement);

        if ($user instanceof User) {
            $inscription->setPrenom($user->getPrenom() ?? '');
            $inscription->setNom($user->getNom() ?? '');
            $inscription->setEmail($user->getEmail() ?? '');
            if ($user->getTelephone()) {
                $inscription->setTelephone($user->getTelephone());
            }
        }

        $form = $this->createForm(InscriptionEvenementType::class, $inscription, [
            'with_identity_fields' => !($user instanceof User),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $inscription->getEmail();
            if ($inscriptionRepo->findOneByEvenementAndEmail($evenement, $email) !== null) {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement avec cette adresse email.');
                return $this->redirectToRoute('front_evenement_show', ['id' => $evenement->getId()]);
            }
            $em->persist($inscription);
            $em->flush();
            $this->addFlash('success', 'Votre inscription a bien été enregistrée. Vous recevrez les informations pratiques par email si besoin.');
            return $this->redirectToRoute('front_evenement_show', ['id' => $evenement->getId()]);
        }

        $holidayDebut = $holidayService->getHolidayName($evenement->getDateDebut());
        $holidayFin = $holidayService->getHolidayName($evenement->getDateFin());

        return $this->render('front/evenement_show.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
            'holiday_debut' => $holidayDebut,
            'holiday_fin' => $holidayFin,
            'already_inscrit' => $alreadyInscrit,
        ]);
    }

    #[Route('/mes-inscriptions', name: 'front_mes_inscriptions')]
    public function mesInscriptions(Request $request, InscriptionEvenementRepository $repository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('connexion');
        }

        $q = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'dateDebut');
        $order = $request->query->get('order', 'DESC');

        $inscriptions = $repository->searchForEmailWithEvenement(
            $user->getEmail() ?? '',
            (string) $q,
            (string) $sort,
            (string) $order
        );

        return $this->render('front/mes_inscriptions.html.twig', [
            'inscriptions' => $inscriptions,
            'q' => (string) $q,
            'sort' => (string) $sort,
            'order' => (string) $order,
        ]);
    }

    #[Route('/suivi', name: 'suivi_front_dashboard')]
    public function suivi(): Response
    {
        return $this->render('front/suivi.html.twig');
    }
}