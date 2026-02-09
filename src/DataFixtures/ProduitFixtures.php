<?php

namespace App\DataFixtures;

use App\Entity\Produit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProduitFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Suppléments nutritionnels
        $produits = [
            // Suppléments nutritionnels
            ['nom' => 'Oméga-3 Premium', 'categorie' => 'Suppléments nutritionnels', 'prix' => 29.99, 'stock' => 50, 'description' => 'Complément à base d\'huile de poisson riche en EPA et DHA. 60 capsules molles, dosage optimal.'],
            ['nom' => 'Magnésium Liposomal', 'categorie' => 'Suppléments nutritionnels', 'prix' => 34.99, 'stock' => 40, 'description' => 'Magnésium de haute biodisponibilité pour meilleure absorption. 90 capsules pour 3 mois.'],
            ['nom' => 'Vitamine D3 2000 UI', 'categorie' => 'Suppléments nutritionnels', 'prix' => 19.99, 'stock' => 60, 'description' => 'Vitamine D3 naturelle pour os et immunité. 120 capsules, forme biodisponible.'],
            ['nom' => 'Curcumine Bioactive', 'categorie' => 'Suppléments nutritionnels', 'prix' => 39.99, 'stock' => 35, 'description' => 'Extrait de curcuma avec poivre noir pour meilleure absorption. 60 capsules premium.'],

            // Équipements de monitoring
            ['nom' => 'Tensiometre brachial numerique', 'categorie' => 'Équipements de monitoring', 'prix' => 79.99, 'stock' => 20, 'description' => 'Moniteur de tension artérielle précis et rapide. Mémoire de 60 mesures, écran LED grand format.'],
            ['nom' => 'Glucometre connecte', 'categorie' => 'Équipements de monitoring', 'prix' => 89.99, 'stock' => 15, 'description' => 'Appareil de mesure de glucose avec application mobile. Synchronisation automatique et historique détaillé.'],
            ['nom' => 'Balance intelligente', 'categorie' => 'Équipements de monitoring', 'prix' => 99.99, 'stock' => 25, 'description' => 'Balance avec analyse de composition corporelle (poids, eau, masse musculaire, etc.). Connectée via WiFi.'],
            ['nom' => 'Oxymétre de pouls', 'categorie' => 'Équipements de monitoring', 'prix' => 49.99, 'stock' => 30, 'description' => 'Capteur de saturation en oxygène et fréquence cardiaque. Petit format, résultats instantanés.'],

            // Bien-être et accessoires
            ['nom' => 'Tapis-yoga premium', 'categorie' => 'Bien-être et accessoires', 'prix' => 59.99, 'stock' => 40, 'description' => 'Tapis éco-responsable antidérapant. Épaisseur 5mm pour confort optimal et stabilité.'],
            ['nom' => 'Bouteille-hydratation intelligente', 'categorie' => 'Bien-être et accessoires', 'prix' => 44.99, 'stock' => 25, 'description' => 'Gourde avec capteur IoT qui vous rappelle de boire. Écran LED affichant consommation journalière.'],
            ['nom' => 'Bandes-elastiques', 'categorie' => 'Bien-être et accessoires', 'prix' => 24.99, 'stock' => 50, 'description' => 'Ensemble de 5 bandes de résistance couleurs. Idéales pour renforcement musculaire et rééducation.'],
            ['nom' => 'Pistolet-massage', 'categorie' => 'Bien-être et accessoires', 'prix' => 149.99, 'stock' => 18, 'description' => 'Masseur vibrant pour relâchement musculaire. 5 intensités, batterie rechargeable, silencieux.'],
            ['nom' => 'Chaussettes-compression', 'categorie' => 'Bien-être et accessoires', 'prix' => 34.99, 'stock' => 35, 'description' => 'Chaussettes médicales pour améliorer la circulation. Classe 2 de compression progressive.'],
            ['nom' => 'Tracker-sommeil', 'categorie' => 'Bien-être et accessoires', 'prix' => 129.99, 'stock' => 22, 'description' => 'Capteur chevet pour analyse du sommeil. Rapporte durée, qualité et phases REM.'],

            // Produits spécialisés
            ['nom' => 'Kit-diabete', 'categorie' => 'Produits spécialisés', 'prix' => 159.99, 'stock' => 12, 'description' => 'Kit de gestion du diabète avec glucomètre, lecteur, lancettes et carnet de suivi intégré.'],
            ['nom' => 'Bundle-cardiaque', 'categorie' => 'Produits spécialisés', 'prix' => 119.99, 'stock' => 20, 'description' => 'Ensemble produits pour santé cardiovasculaire : Oméga-3, CoQ10, magnésium et guide nutritionnel.'],
            ['nom' => 'Support-articulation', 'categorie' => 'Produits spécialisés', 'prix' => 89.99, 'stock' => 25, 'description' => 'Combo arthrite : Glucosamine, chondroïtine, MSM et genouillères médicales de classe 2.'],
            ['nom' => 'Systeme-respiratoire', 'categorie' => 'Produits spécialisés', 'prix' => 99.99, 'stock' => 15, 'description' => 'Kit respiration : Peak flow meter, inhalateur valvalisé, suppléments respiratoires et mouchoirs humidifiants.'],
            ['nom' => 'Kit-allergie', 'categorie' => 'Produits spécialisés', 'prix' => 79.99, 'stock' => 20, 'description' => 'Gestion allergies : Anti-histaminiques naturels, spray nasal, masque protecteur et journal symptômes.'],
            ['nom' => 'Kit-recuperation', 'categorie' => 'Produits spécialisés', 'prix' => 139.99, 'stock' => 18, 'description' => 'Récupération chirurgicale : Compression médicale, oreiller ergonomique, pansements avancés et guide réhabilitation.'],
        ];

        foreach ($produits as $data) {
            $produit = new Produit();
            $produit->setNom($data['nom']);
            $produit->setCategorie($data['categorie']);
            $produit->setPrix($data['prix']);
            $produit->setStock($data['stock']);
            $produit->setDescription($data['description']);
            $produit->setIsActive(true);

            $manager->persist($produit);
        }

        $manager->flush();
    }
}
