<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Entity\User;
use App\Form\InscriptionEvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Repository\ProduitRepository;
use App\Service\HolidayService;
use App\Service\InscriptionEvenementMailer;
use App\Service\InscriptionTicketUrlService;
use App\Service\MapGeocodingService;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        if ($this->getUser() && $this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_ADMIN')) {
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
        return $this->redirectToRoute('app_login');
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
    public function boutique(Request $request, ProduitRepository $produitRepository): Response
    {
        $keyword = $request->query->get('keyword', '');
        $priceMin = $request->query->get('priceMin', 0);
        $priceMax = $request->query->get('priceMax', 999999);

        if ($keyword) {
            $produits = $produitRepository->searchByKeyword($keyword);
        } elseif ($priceMin || $priceMax) {
            $produits = $produitRepository->findByPriceRange((float) $priceMin, (float) $priceMax);
        } else {
            $produits = $produitRepository->findAllActive();
        }

        $categories = $produitRepository->findAllCategories();

        return $this->render('front/boutique.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'keyword' => $keyword,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
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
        InscriptionEvenementRepository $inscriptionRepo,
        MapGeocodingService $mapGeocodingService,
        WeatherService $weatherService,
        InscriptionEvenementMailer $inscriptionEvenementMailer,
        InscriptionTicketUrlService $ticketUrlService
    ): Response {
        if ($evenement->getStatut() === Evenement::STATUT_REFUSE) {
            throw $this->createNotFoundException('Evenement non disponible.');
        }

        $user = $this->getUser();
        $alreadyInscrit = false;
        $ticketUrl = null;
        $sessionTicketKey = 'event_ticket_' . $evenement->getId();
        if ($user instanceof User && $user->getEmail()) {
            $existingInscription = $inscriptionRepo->findOneByEvenementAndEmail($evenement, $user->getEmail());
            $alreadyInscrit = $existingInscription !== null;
            if ($existingInscription instanceof InscriptionEvenement) {
                $ticketUrl = $ticketUrlService->generateSignedTicketUrl($existingInscription);
            }
        } else {
            $ticketFromSession = $request->getSession()?->remove($sessionTicketKey);
            if (is_string($ticketFromSession) && $ticketFromSession !== '') {
                $ticketUrl = $ticketFromSession;
            }
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
                $this->addFlash('warning', 'Vous Ãªtes dÃ©jÃ  inscrit Ã  cet Ã©vÃ©nement avec cette adresse email.');
                return $this->redirectToRoute('front_evenement_show', ['id' => $evenement->getId()]);
            }
            $em->persist($inscription);
            $em->flush();
            $ticketUrl = $ticketUrlService->generateSignedTicketUrl($inscription);
            $inscriptionEvenementMailer->sendConfirmation($inscription, $ticketUrl);
            $this->addFlash('success', 'Votre inscription a bien Ã©tÃ© enregistrÃ©e. Vous recevrez les informations pratiques par email si besoin.');
            if (!$user instanceof User) {
                $request->getSession()?->set($sessionTicketKey, $ticketUrl);
            }

            return $this->redirectToRoute('front_evenement_show', ['id' => $evenement->getId()]);
        }

        $holidayDebut = $holidayService->getHolidayName($evenement->getDateDebut());
        $holidayFin = $holidayService->getHolidayName($evenement->getDateFin());
        $mapLocation = $mapGeocodingService->resolveLocation($evenement->getLieu());
        $weather = $weatherService->getEventWeather($evenement->getDateDebut(), $evenement->getLieu());

        return $this->render('front/evenement_show.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
            'holiday_debut' => $holidayDebut,
            'holiday_fin' => $holidayFin,
            'map_location' => $mapLocation,
            'already_inscrit' => $alreadyInscrit,
            'ticket_url' => $ticketUrl,
            'weather' => $weather,
        ]);
    }

    #[Route('/inscriptions/{id}/ticket', name: 'front_inscription_ticket', requirements: ['id' => '\d+'])]
    public function inscriptionTicket(
        Request $request,
        InscriptionEvenement $inscription,
        InscriptionTicketUrlService $ticketUrlService
    ): Response {
        if (!$ticketUrlService->isRequestSignatureValid($request)) {
            throw $this->createAccessDeniedException('Lien de ticket invalide ou expire.');
        }

        $evenement = $inscription->getEvenement();
        if ($evenement === null) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        $ticketUrl = $ticketUrlService->generateSignedTicketUrl($inscription);

        return $this->render('front/inscription_ticket.html.twig', [
            'inscription' => $inscription,
            'evenement' => $evenement,
            'ticket_url' => $ticketUrl,
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
