# 🌍 Géolocalisation Automatique via IP-API

## Vue d'ensemble

Cette implémentation remplace le système d'adresse textuelle par une **géolocalisation automatique basée sur l'IP du client**. Utilisant l'API gratuite [ip-api.com](https://ip-api.com/), elle détecte automatiquement :

✅ **Données collectées :**
- Adresse IP du client
- Pays
- Région/État
- Ville
- Coordonnées GPS (latitude/longitude)
- Fuseau horaire
- Opérateur Internet (ISP)

---

## Architecture de la solution

### 1. **Service de Géolocalisation** ([src/Service/GeoLocationService.php](src/Service/GeoLocationService.php))
Service centralisé pour gérer les appels à ip-api :
```php
$location = $geoLocationService->getLocationByIp('8.8.8.8');
// Retourne :
// [
//     'country' => 'United States',
//     'city' => 'Mountain View',
//     'latitude' => 37.42,
//     'longitude' => -122.08,
//     ...
// ]
```

### 2. **Entité Commande modifiée**
Anciens champs supprimés et remplacés par :
- `ipAddress` : Adresse IP du client
- `country` : Pays automatiquement détecté
- `city` : Ville automatiquement détectée
- `region` : Région automatiquement détectée
- `latitude` / `longitude` : Coordonnées GPS
- `timezone` : Fuseau horaire
- `isp` : Opérateur Internet

### 3. **API REST** ([src/Controller/GeolocationApiController.php](src/Controller/GeolocationApiController.php))
Endpoints pour accéder à la géolocalisation :

```bash
# Géolocaliser une IP spécifique
GET /api/geolocation/ip/8.8.8.8

# Géolocaliser le client actuel
GET /api/geolocation/client

# Calculer la distance entre 2 points GPS
POST /api/geolocation/distance
{
  "lat1": 48.8566,
  "lon1": 2.3522,
  "lat2": 43.2965,
  "lon2": 5.3698
}
```

### 4. **Frontend - Géolocalisation JavaScript** ([public/js/geolocation.js](public/js/geolocation.js))
Script côté client qui :
1. Appelle ip-api automatiquement au chargement
2. Ajoute l'IP au formulaire caché
3. Affiche les informations de localisation

### 5. **Formulaire de Commande**
Remplace les champs d'adresse texte par une détection automatique :
```html
<input type="hidden" name="ipAddress" class="geolocation-ip">
<div id="geolocation-info"></div>
```

---

## Installation et mise à jour

### 1. Appliquer les migrations
```bash
php bin/console doctrine:migrations:migrate
```

Cela va :
- ✅ Créer les colonnes de géolocalisation
- ❌ Supprimer l'ancienne colonne `adresse`

### 2. Vider le cache
```bash
php bin/console cache:clear
```

---

## Utilisation

### Dans un Contrôleur
```php
public function checkout(Request $request) {
    $clientIp = $request->getClientIp();
    
    $commande = $this->commandeService->createCommande(
        $user,
        $items,
        $clientIp,  // Remplace l'adresse textuelle
        'stripe'
    );
}
```

### Via l'API REST
```bash
# Exemple : Géolocaliser une adresse IP
curl "https://votre-app.com/api/geolocation/ip/8.8.8.8"

# Réponse :
{
  "status": "success",
  "data": {
    "ip": "8.8.8.8",
    "country": "United States",
    "city": "Mountain View",
    "latitude": 37.4192,
    "longitude": -122.0574,
    "timezone": "America/Los_Angeles",
    "isp": "Google LLC"
  }
}
```

### Depuis JavaScript
```javascript
// Exemple : Obtenir les données du client actuel
fetch('/api/geolocation/client')
  .then(res => res.json())
  .then(data => {
    console.log(`Vous êtes à ${data.data.city}, ${data.data.country}`);
  });
```

---

## Affichage de l'adresse formatée

L'entité Commande fournit une méthode pour obtenir l'adresse formatée :

```php
$commande->getAdresse(); 
// Retourne : "Île-de-France, Paris, France"
```

Dans un template Twig :
```twig
<p>Localisation : {{ commande.adresse }}</p>
```

---

## Limitations et considérations

⚠️ **Limites de ip-api** :
- **Limite gratuite** : 45 requêtes/minute
- **Avec clé API gratuite** : 30 000 requêtes/mois
- **Pas de VPN/Proxy** : Détecte l'IP sortante
- **Précision** : Niveau ville (±50-100 km)

✅ **Points positifs** :
- ✅ 100% gratuit (pas de carte bancaire)
- ✅ Aucune authentification requise
- ✅ Pas de clé API nécessaire (version gratuite)
- ✅ Réponses rapides (~50-100ms)
- ✅ Données précises et actualisées

---

## Tests

### Test manuel
1. Allez sur la page de checkout
2. Observez le message "Détection de votre localisation en cours..."
3. Après quelques secondes, votre localisation s'affiche :
   ```
   ✓ Localisation détectée
   📍 Paris, Île-de-France, France
   Coordonnées: 48.8566°N, 2.3522°E | Fuseau: Europe/Paris | Opérateur: Orange Telecom
   ```

### Test via API
```bash
# Test avec une IP publique (Google DNS)
curl "http://127.0.0.1:8000/api/geolocation/ip/8.8.8.8"

# Test avec l'IP du client
curl "http://127.0.0.1:8000/api/geolocation/client"

# Test de calcul de distance (Paris ↔ Marseille)
curl -X POST "http://127.0.0.1:8000/api/geolocation/distance" \
  -H "Content-Type: application/json" \
  -d '{
    "lat1": 48.8566,
    "lon1": 2.3522,
    "lat2": 43.2965,
    "lon2": 5.3698
  }'
```

---

## Structure de données

### Table `commande`
| Colonne | Type | Nullable | Description |
|---------|------|----------|-------------|
| ip_address | VARCHAR(45) | YES | Adresse IP du client |
| country | VARCHAR(50) | YES | Pays détecté |
| city | VARCHAR(50) | YES | Ville détectée |
| region | VARCHAR(50) | YES | Région/État détecté |
| latitude | DOUBLE | YES | Latitude (GPS) |
| longitude | DOUBLE | YES | Longitude (GPS) |
| timezone | VARCHAR(50) | YES | Fuseau horaire (ex: Europe/Paris) |
| isp | VARCHAR(100) | YES | Nom de l'opérateur Internet |

---

## Fichiers modifiés

📝 **Entités** :
- ✅ [src/Entity/Commande.php](src/Entity/Commande.php) - Remplacement des champs d'adresse

📝 **Services** :
- ✅ [src/Service/GeoLocationService.php](src/Service/GeoLocationService.php) - Nouveau service
- ✅ [src/Service/CommandeService.php](src/Service/CommandeService.php) - Injection du GeoLocationService

📝 **Contrôleurs** :
- ✅ [src/Controller/CommandeController.php](src/Controller/CommandeController.php) - Utilisation de l'IP du client
- ✅ [src/Controller/GeolocationApiController.php](src/Controller/GeolocationApiController.php) - Nouveau contrôleur API

📝 **Formulaires** :
- ✅ [src/Form/AdvancedCommandeFormType.php](src/Form/AdvancedCommandeFormType.php) - Champ caché pour l'IP

📝 **Templates** :
- ✅ [templates/commande/checkout.html.twig](templates/commande/checkout.html.twig) - Interface de géolocalisation

📝 **Frontend** :
- ✅ [public/js/geolocation.js](public/js/geolocation.js) - Script de géolocalisation

📝 **Migrations** :
- ✅ [migrations/Version20260221100000.php](migrations/Version20260221100000.php) - Mise à jour du schéma

---

## Cas d'usage avancés

### 1. Filtrer les commandes par région
```php
$commandeRepository->findByRegion('Île-de-France');
```

### 2. Ajouter un calcul de frais de port géographiques
```php
$distance = $this->geoLocationService->calculateDistance(
    $warehouse->getLatitude(),
    $warehouse->getLongitude(),
    $commande->getLatitude(),
    $commande->getLongitude()
);

$shippingCost = $distance > 1000 ? 15.99 : 5.99;
```

### 3. Analyser la distribution des clients par localisation
```sql
SELECT country, city, COUNT(*) as count 
FROM commande 
GROUP BY country, city 
ORDER BY count DESC;
```

---

## FAQ

**Q: Les données de géolocalisation sont-elles précises ?**
A: Non, c'est au niveau de la ville (±50-100 km). Pour une précision à l'adresse, utiliser une autre API.

**Q: Que se passe-t-il si la géolocalisation échoue ?**
A: Un message d'avertissement s'affiche, mais la commande peut tout de même être créée sans les données de géolocalisation.

**Q: Est-ce RGPD compliant ?**
A: ⚠️ La détection automatique de localisation via IP peut nécessiter l'accord de l'utilisateur. Vérifiez votre politique de confidacité.

**Q: Comment obtenir une meilleure limite de requêtes ?**
A: Créer un compte gratuit sur ip-api.com pour obtenir 30 000 requêtes/mois ou passer en plan payant.

---

## Support et Maintenance

Pour tous problèmes :
1. Vérifier les logs : `tail -f var/log/dev.log`
2. Vérifier la connectivité avec ip-api : `curl https://ip-api.com/json/`
3. Vérifier les migrations : `php bin/console doctrine:migrations:status`

