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
    public function show(int $id): Response
    {
        $produit = $this->produitRepository->findOneBy(['id' => $id, 'isActive' => true]);

        if (!$produit) {
            $this->addFlash('error', 'Produit non trouvé ou indisponible.');
            return $this->redirectToRoute('boutique');
        }

        return $this->render('produit/show.html.twig', ['produit' => $produit]);
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
