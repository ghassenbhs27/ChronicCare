<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', name: 'produit_')]
class ProduitController extends AbstractController
{
    public function __construct(private readonly ProduitRepository $produitRepository)
    {
    }

    /**
     * Commande rapide depuis la fiche produit (patient)
     */
    #[Route('/{id}/quick-order', name: 'quick_order', methods: ['POST'])]
    public function quickOrder(int $id, Request $request, \Doctrine\ORM\EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            $this->addFlash('error', 'Le produit demandé n\'existe pas.');
            return $this->redirectToRoute('boutique');
        }

        $nom = $request->request->get('nom', '');
        $prenom = $request->request->get('prenom', '');
        $email = $request->request->get('email', '');
        $telephone = $request->request->get('telephone', '');
        $etat = $request->request->get('etat', '');
        $adresse = $request->request->get('adresse', '');

        if (empty($nom) || empty($email) || empty($telephone) || empty($adresse)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }

        $simulationUser = $userRepository->findOneBy(['email' => 'simulation@chroniccare.local']);
        if (!$simulationUser) {
            $this->addFlash('error', 'Une erreur système s\'est produite.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }

        try {
            $commande = new \App\Entity\Commande();
            $commande->setNumeroCommande(uniqid('CC-'));
            $commande->setStatut('en_attente');
            $commande->setTotal($produit->getPrix());
            $commande->setUtilisateur($simulationUser);
            $commande->setAdresse($adresse . " | " . $nom . " " . $prenom . " | " . $email . " | " . $telephone . " | " . $etat);

            $ligne = new \App\Entity\LigneCommande();
            $ligne->setProduit($produit);
            $ligne->setQuantite(1);
            $ligne->setPrixUnitaire($produit->getPrix());
            $ligne->setSousTotal($produit->getPrix());
            $ligne->setCommande($commande);

            $em->persist($commande);
            $em->persist($ligne);
            $em->flush();

            $this->addFlash('success', 'Votre commande a été créée avec succès !');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur s\'est produite lors de la création de la commande.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $produits = $this->produitRepository->findAllActive();
        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, UserRepository $userRepository): Response
    {
        $produit = $this->produitRepository->findOneBy(['id' => $id, 'isActive' => true]);

        if (!$produit) {
            $this->addFlash('error', 'Produit non trouvé ou indisponible.');
            return $this->redirectToRoute('boutique');
        }

        // Liste des patients pour les professionnels
        $patients = [];
        if ($this->isGranted('ROLE_NUTRITIONNISTE') || $this->isGranted('ROLE_COACH')) {
            $patients = $userRepository->findByRole('ROLE_PATIENT');
        }

        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
            'patients' => $patients,
        ]);
    }

    /**
     * Recommander un produit à un patient (pour nutritionnistes/coachs)
     */
    #[Route('/{id}/recommend', name: 'recommend_to_patient', methods: ['POST'])]
    public function recommendToPatient(
        int $id, 
        Request $request, 
        UserRepository $userRepository,
        \Doctrine\ORM\EntityManagerInterface $em,
        \Symfony\Component\Mailer\MailerInterface $mailer
    ): Response {
        if (!$this->isGranted('ROLE_NUTRITIONNISTE') && !$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException('Accès réservé aux professionnels.');
        }
        
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            $this->addFlash('error', 'Produit non trouvé.');
            return $this->redirectToRoute('boutique');
        }

        $patientId = $request->request->get('patient_id');
        $message = $request->request->get('message', '');
        
        $patient = $userRepository->find($patientId);
        if (!$patient) {
            $this->addFlash('error', 'Patient non trouvé.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }

        /** @var \App\Entity\User $pro */
        $pro = $this->getUser();
        
        // Envoyer un email au patient
        try {
            $email = (new \Symfony\Component\Mime\Email())
                ->from('noreply@chroniccare.org')
                ->to($patient->getEmail())
                ->subject('Recommandation de produit - ' . $produit->getNom())
                ->html(sprintf(
                    '<h2>Recommandation de votre %s</h2>
                    <p>Bonjour %s,</p>
                    <p>Votre %s <strong>%s %s</strong> vous recommande le produit suivant :</p>
                    <div style="border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 8px;">
                        <h3>%s</h3>
                        <p>Prix: %.2f €</p>
                        <p>%s</p>
                    </div>
                    %s
                    <p><a href="http://localhost:8000/products/%d" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Voir le produit</a></p>
                    <p>Cordialement,<br>L\'équipe ChronicCare</p>',
                    in_array('ROLE_NUTRITIONNISTE', $pro->getRoles()) ? 'nutritionniste' : 'coach',
                    $patient->getPrenom(),
                    in_array('ROLE_NUTRITIONNISTE', $pro->getRoles()) ? 'nutritionniste' : 'coach',
                    $pro->getPrenom(),
                    $pro->getNom(),
                    $produit->getNom(),
                    $produit->getPrix(),
                    $produit->getDescription() ?? '',
                    $message ? '<p><strong>Message personnalisé :</strong> ' . htmlspecialchars($message) . '</p>' : '',
                    $produit->getId()
                ));
            
            $mailer->send($email);
            $this->addFlash('success', 'Recommandation envoyée à ' . $patient->getPrenom() . ' ' . $patient->getNom() . ' avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('warning', 'La recommandation a été enregistrée mais l\'email n\'a pas pu être envoyé.');
        }

        return $this->redirectToRoute('produit_show', ['id' => $id]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $keyword = $request->query->get('keyword', '');
        $categorie = $request->query->get('categorie', '');
        $priceMin = (float) $request->query->get('priceMin', 0);
        $priceMax = (float) $request->query->get('priceMax', 999999);

        $results = [];
        if ($keyword) {
            $results = $this->produitRepository->searchByKeyword($keyword);
        } elseif ($categorie) {
            $results = $this->produitRepository->findByCategorie($categorie);
        } elseif ($priceMin > 0 || $priceMax < 999999) {
            $results = $this->produitRepository->findByPriceRange($priceMin, $priceMax);
        }

        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/search.html.twig', [
            'results' => $results,
            'keyword' => $keyword,
            'categorie' => $categorie,
            'categories' => $categories,
        ]);
    }
}
