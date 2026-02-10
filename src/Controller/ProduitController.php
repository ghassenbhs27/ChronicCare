<?php

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProduitController extends AbstractController
{
    /**
     * Commande rapide depuis la fiche produit (patient)
     */
    #[Route('/products/{id}/quick-order', name: 'produit_quick_order', methods: ['POST'])]
    public function quickOrder(int $id, Request $request, \Doctrine\ORM\EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $produit = $this->produitRepository->find($id);
        if (!$produit) {
            $this->addFlash('error', 'Le produit demandé n\'existe pas. Veuillez sélectionner un produit valide.');
            return $this->redirectToRoute('boutique');
        }

        // Valider les données du formulaire
        $nom = $request->request->get('nom', '');
        $prenom = $request->request->get('prenom', '');
        $email = $request->request->get('email', '');
        $telephone = $request->request->get('telephone', '');
        $etat = $request->request->get('etat', '');
        $adresse = $request->request->get('adresse', '');

        // Vérifier que les données obligatoires sont présentes
        if (empty($nom) || empty($email) || empty($telephone) || empty($adresse)) {
            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }

        // Charger l'utilisateur de simulation
        $simulationUser = $userRepository->findOneBy(['email' => 'simulation@chroniccare.local']);
        if (!$simulationUser) {
            $this->addFlash('error', 'Une erreur système s\'est produite. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }

        try {
            // Créer la commande
            $commande = new \App\Entity\Commande();
            $commande->setNumeroCommande(uniqid('CC-'));
            $commande->setStatut('en_attente');
            $commande->setTotal($produit->getPrix());
            $commande->setUtilisateur($simulationUser);
            // Stocker les infos patient dans le champ adresse
            $commande->setAdresse($adresse . " | " . $nom . " " . $prenom . " | " . $email . " | " . $telephone . " | " . $etat);

            // Créer la ligne de commande
            $ligne = new \App\Entity\LigneCommande();
            $ligne->setProduit($produit);
            $ligne->setQuantite(1);
            $ligne->setPrixUnitaire($produit->getPrix());
            $ligne->setSousTotal($produit->getPrix());
            $ligne->setCommande($commande);

            $em->persist($commande);
            $em->persist($ligne);
            $em->flush();

            $this->addFlash('success', 'Votre commande a été créée avec succès ! Nos experts vous rappelleront bientôt.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur s\'est produite lors de la création de la commande. Veuillez réessayer.');
            return $this->redirectToRoute('produit_show', ['id' => $id]);
        }
    }

    private $produitRepository;

    public function __construct(ProduitRepository $produitRepository)
    {
        $this->produitRepository = $produitRepository;
    }

    /**
     * Afficher le catalogue des produits
     */
    #[Route('/', name: 'list')]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 12;

        $produits = $this->produitRepository->findAllActive();
        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Afficher les détails d'un produit
     */
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $produit = $this->produitRepository->findOneBy(['id' => $id, 'isActive' => true]);

        if (!$produit) {
            $this->addFlash('error', 'Le produit demandé n\'existe pas ou n\'est pas disponible.');
            return $this->redirectToRoute('boutique');
        }

        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    /**
     * Rechercher des produits
     */
    #[Route('/search', name: 'search')]
    public function search(Request $request): Response
    {
        $keyword = $request->query->get('keyword', '');
        $categorie = $request->query->get('categorie', '');
        $priceMin = $request->query->get('priceMin', 0);
        $priceMax = $request->query->get('priceMax', 999999);

        $results = [];

        if ($keyword) {
            $results = $this->produitRepository->searchByKeyword($keyword);
        } elseif ($categorie) {
            $results = $this->produitRepository->findByCategorie($categorie);
        } elseif ($priceMin || $priceMax) {
            $results = $this->produitRepository->findByPriceRange((float) $priceMin, (float) $priceMax);
        }

        $categories = $this->produitRepository->findAllCategories();

        return $this->render('produit/search.html.twig', [
            'results' => $results,
            'keyword' => $keyword,
            'categorie' => $categorie,
            'categories' => $categories,
        ]);
    }

    /**
     * Liste des produits
     */
    #[Route('/products', name: 'produit_list', methods: ['GET'])]
    public function list(ProduitRepository $produitRepository): Response
    {
        $produits = $produitRepository->findAll();

        return $this->render('produit/list.html.twig', [
            'produits' => $produits,
        ]);
    }
}
