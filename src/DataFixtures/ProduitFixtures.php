<?php

namespace App\DataFixtures;

use App\Entity\Produit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProduitFixtures extends Fixture
{
    public static function getGroups(): array
    {
        return ['produit'];
    }

    public function load(ObjectManager $manager): void
    {
        $produits = [
            ['nom' => 'Oméga-3 Premium', 'categorie' => 'Suppléments nutritionnels', 'prix' => '29.99', 'stock' => 50, 'description' => 'Complément à base d\'huile de poisson riche en EPA et DHA. 60 capsules molles, dosage optimal.'],
            ['nom' => 'Magnésium Liposomal', 'categorie' => 'Suppléments nutritionnels', 'prix' => '34.99', 'stock' => 40, 'description' => 'Magnésium de haute biodisponibilité pour meilleure absorption. 90 capsules pour 3 mois.'],
            ['nom' => 'Vitamine D3 2000 UI', 'categorie' => 'Suppléments nutritionnels', 'prix' => '19.99', 'stock' => 60, 'description' => 'Vitamine D3 naturelle pour os et immunité. 120 capsules, forme biodisponible.'],
            ['nom' => 'Curcumine Bioactive', 'categorie' => 'Suppléments nutritionnels', 'prix' => '39.99', 'stock' => 35, 'description' => 'Extrait de curcuma avec poivre noir pour meilleure absorption. 60 capsules premium.'],
            ['nom' => 'Tensiomètre brachial numérique', 'categorie' => 'Équipements de monitoring', 'prix' => '79.99', 'stock' => 20, 'description' => 'Moniteur de tension artérielle précis. Mémoire de 60 mesures, écran LED.'],
            ['nom' => 'Glucomètre connecté', 'categorie' => 'Équipements de monitoring', 'prix' => '89.99', 'stock' => 15, 'description' => 'Mesure de glucose avec application mobile. Synchronisation automatique.'],
            ['nom' => 'Balance intelligente', 'categorie' => 'Équipements de monitoring', 'prix' => '99.99', 'stock' => 25, 'description' => 'Analyse de composition corporelle. Connectée via WiFi.'],
            ['nom' => 'Tapis de yoga premium', 'categorie' => 'Bien-être et accessoires', 'prix' => '59.99', 'stock' => 40, 'description' => 'Tapis antidérapant. Épaisseur 5mm pour confort optimal.'],
            ['nom' => 'Kit bandes élastiques', 'categorie' => 'Bien-être et accessoires', 'prix' => '24.99', 'stock' => 50, 'description' => '5 bandes de résistance. Idéales pour renforcement musculaire.'],
            ['nom' => 'Kit diabète complet', 'categorie' => 'Produits spécialisés', 'prix' => '159.99', 'stock' => 12, 'description' => 'Glucomètre, lancettes et carnet de suivi intégré.'],
        ];

        foreach ($produits as $data) {
            $produit = new Produit();
            $produit->setNom($data['nom']);
            $produit->setCategorie($data['categorie']);
            $produit->setPrix((string) $data['prix']);
            $produit->setStock($data['stock']);
            $produit->setDescription($data['description']);
            $produit->setIsActive(true);
            $manager->persist($produit);
        }

        $manager->flush();
    }
}
