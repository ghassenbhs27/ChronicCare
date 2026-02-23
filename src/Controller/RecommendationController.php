<?php

namespace App\Controller;

use App\Entity\ProfilMedical;
use App\Form\ProfilMedicalType;
use App\Repository\ProduitRepository;
use App\Repository\ProfilMedicalRepository;
use App\Service\DrugCompatibilityService;
use App\Service\ProductRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/patient/recommandations')]
#[IsGranted('ROLE_USER')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private readonly ProductRecommendationService $recommendationService,
        private readonly DrugCompatibilityService $compatibilityService,
        private readonly ProduitRepository $produitRepository,
        private readonly ProfilMedicalRepository $profilMedicalRepository,
        private readonly EntityManagerInterface $em
    ) {}

    /**
     * Page principale des recommandations personnalisées
     */
    #[Route('', name: 'patient_recommendations', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $profilMedical = $user->getProfilMedical();

        // Recommandations personnalisées
        $recommendations = $this->recommendationService->getPersonalizedRecommendations($user, 8);

        // Produits achetés par patients similaires
        $similarPurchases = $this->recommendationService->getSimilarPatientsPurchases($user, 4);

        // Produits populaires (fallback)
        $popularProducts = $this->produitRepository->findBy(
            ['isActive' => true],
            ['popularite' => 'DESC'],
            6
        );

        return $this->render('recommendation/index.html.twig', [
            'profil' => $profilMedical,
            'recommendations' => $recommendations,
            'similarPurchases' => $similarPurchases,
            'popularProducts' => $popularProducts,
            'aiAvailable' => $this->recommendationService->isAIAvailable(),
        ]);
    }

    /**
     * Formulaire de profil médical
     */
    #[Route('/profil', name: 'patient_profil_medical', methods: ['GET', 'POST'])]
    public function editProfil(Request $request): Response
    {
        $user = $this->getUser();
        $profil = $user->getProfilMedical();

        if (!$profil) {
            $profil = new ProfilMedical();
            $profil->setUtilisateur($user);
        }

        $form = $this->createForm(ProfilMedicalType::class, $profil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($profil);
            $this->em->flush();

            $this->addFlash('success', 'Votre profil médical a été mis à jour. Vos recommandations seront maintenant personnalisées.');
            return $this->redirectToRoute('patient_recommendations');
        }

        return $this->render('recommendation/profil_form.html.twig', [
            'form' => $form->createView(),
            'profil' => $profil,
        ]);
    }

    /**
     * API: Vérifie la compatibilité d'un produit
     */
    #[Route('/check/{id}', name: 'patient_check_product', methods: ['GET'])]
    public function checkProduct(int $id): JsonResponse
    {
        $user = $this->getUser();
        $profil = $user->getProfilMedical();
        $produit = $this->produitRepository->find($id);

        if (!$produit) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        if (!$profil) {
            return $this->json([
                'compatible' => true,
                'message' => 'Complétez votre profil médical pour des recommandations personnalisées',
                'needsProfile' => true
            ]);
        }

        $analysis = $this->compatibilityService->analyzeCompatibility($produit, $profil);
        $summary = $this->compatibilityService->getAlertSummary($analysis);

        return $this->json([
            'compatible' => $analysis['compatible'],
            'score' => $analysis['score'],
            'summary' => $summary,
            'alertes' => $analysis['alertes'],
            'warnings' => $analysis['warnings'],
            'recommendations' => $analysis['recommendations'],
            'analysisSource' => $analysis['analysisSource'],
        ]);
    }

    /**
     * API: Vérifie la compatibilité du panier
     */
    #[Route('/check-cart', name: 'patient_check_cart', methods: ['POST'])]
    public function checkCart(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $profil = $user->getProfilMedical();

        if (!$profil) {
            return $this->json([
                'compatible' => true,
                'needsProfile' => true,
                'message' => 'Créez votre profil médical pour une analyse de compatibilité'
            ]);
        }

        $data = json_decode($request->getContent(), true);
        $productIds = $data['products'] ?? [];

        $produits = [];
        foreach ($productIds as $id) {
            $produit = $this->produitRepository->find($id);
            if ($produit) {
                $produits[] = $produit;
            }
        }

        $analysis = $this->recommendationService->checkCartCompatibility($produits, $profil);

        return $this->json($analysis);
    }

    /**
     * Widget de recommandations pour intégration dans d'autres pages
     */
    #[Route('/widget', name: 'patient_recommendations_widget', methods: ['GET'])]
    public function widget(): Response
    {
        $user = $this->getUser();
        $recommendations = $this->recommendationService->getPersonalizedRecommendations($user, 4);

        return $this->render('recommendation/_widget.html.twig', [
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * API: Récupère des recommandations en AJAX
     */
    #[Route('/api/suggestions', name: 'patient_api_suggestions', methods: ['GET'])]
    public function apiSuggestions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = min(12, max(1, (int)$request->query->get('limit', 6)));
        $category = $request->query->get('category');

        $recommendations = $this->recommendationService->getPersonalizedRecommendations($user, $limit);

        // Filtrer par catégorie si spécifié
        if ($category) {
            $recommendations['produits'] = array_filter(
                $recommendations['produits'],
                fn($p) => strtolower($p->getCategorie()) === strtolower($category)
            );
        }

        $data = [];
        foreach ($recommendations['produits'] as $produit) {
            $data[] = [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'prix' => (float)$produit->getPrix(),
                'categorie' => $produit->getCategorie(),
                'description' => substr($produit->getDescription(), 0, 100) . '...',
                'raison' => $recommendations['raisons'][$produit->getId()] ?? 'Recommandé pour vous',
                'stock' => $produit->getStock(),
            ];
        }

        return $this->json([
            'produits' => $data,
            'alertes' => $recommendations['alertes'],
            'source' => $recommendations['source'],
            'total' => count($data)
        ]);
    }
}
