# 🔧 Guide de Résolution des Problèmes de Géolocalisation

## Problème : "Détection de votre localisation en cours..." reste affiché indéfiniment

### ✅ Étape 1 : Vérifier le script JavaScript

1. **Ouvrir la console du navigateur** (F12 → Onglet Console)
2. **Chercher les logs** commençant par `🌍` ou `❌`

### ❌ Erreur : Timeout

**Symptôme :** Message `[Timeout: 5000ms]`

**Cause :** ip-api.com n'est pas accessible (firewall, proxy, réseau bloqué)

**Solution :**
```bash
# 1. Test en ligne de commande
curl https://ip-api.com/json/

# 2. Si ça ne marche pas, le serveur bloque ip-api.com
# → Vérifiez les règles de firewall
# → Demandez à l'administrateur réseau d'autoriser ip-api.com
```

**Fallback automatique :** ✅ L'API serveur `/api/geolocation/client` sera utilisée à la place

---

### ❌ Erreur : "Fetch error" ou "CORS error"

**Symptôme :**
```
❌ Échec IP-API: Failed to fetch
❌ Complètement échoué: Failed to fetch
```

**Cause :** Problème CORS, proxy, ou firewall

**Solution :**
1. Vérifier que le navigateur peut accéder à `https://ip-api.com/json/`
2. Le fallback vers l'API serveur doit fonctionner
3. Vérifier les logs serveur : `tail -f var/log/dev.log`

---

### 📡 Tester les APIs

#### Test IP-API directement

```bash
curl https://ip-api.com/json/
```

Response :
```json
{
  "status": "success",
  "country": "France",
  "city": "Paris",
  "lat": 48.8566,
  "lon": 2.3522,
  ...
}
```

#### Test API Serveur Symfony

```bash
curl http://127.0.0.1:8000/api/geolocation/client
```

Response :
```json
{
  "status": "success",
  "ip": "YOUR_IP",
  "data": {
    "country": "France",
    "city": "Paris",
    ...
  }
}
```

---

### 🔍 Utiliser le fichier de débogage

**URL:** `http://127.0.0.1:8000/debug_geolocation.php`

**Fonctionnalités :**
- ✅ Test IP-API directe
- ✅ Test API Serveur
- ✅ Test du script complet
- 📊 Console de débogage en temps réel
- ℹ️ Infos client

### Étapes :

1. **Ouvrir** `http://127.0.0.1:8000/debug_geolocation.php`
2. **Cliquer sur "Tester IP-API"**
   - Si ✅ → IP-API fonctionne
   - Si ❌ → Firewall bloque ip-api.com
3. **Cliquer sur "Tester API Serveur"**
   - Si ✅ → Fallback fonctionne
   - Si ❌ → Problème avec le contrôleur Symfony

---

## ✅ Solution complète pour la détection lente/figée

### Problème 1 : IP-API inaccessible (CORS/Firewall)

**Symptôme :** Timeout de 5 secondes puis bascule lente vers API serveur

**Solution :**
1. Réduire le timeout de 5000ms à 2000ms dans `public/js/geolocation.js`
2. Autoriser ip-api.com dans le firewall
3. Ou configurer un proxy

**Code à modifier :**
```javascript
const GEOLOCATION_TIMEOUT = 2000; // Réduit de 5000 à 2000ms
```

### Problème 2 : API Serveur lente

**Symptôme :** Même après IP-API, l'API serveur reste lente

**Vérifier :**
```bash
# 1. Vérifier que le contrôleur existe
php bin/console debug:router | grep geolocation

# 2. Tester l'API directement
php bin/console server:run  # Démarrer le serveur
curl http://127.0.0.1:8000/api/geolocation/client
```

### Problème 3 : Pas de fichier JavaScript

**Symptôme :** Console affiche aucun log `🌍`

**Vérifier :**
```bash
# 1. Vérifier que le fichier existe
ls -la public/js/geolocation.js

# 2. Vérifier l'URL correcte dans le template
grep -r "geolocation.js" templates/

# 3. Vider le cache navigateur (Ctrl+Shift+Del)
```

---

## 🚀 Optimisations pour production

### 1. Réduire le timeout si réseau rapide
```javascript
const GEOLOCATION_TIMEOUT = 2000; // Au lieu de 5000ms
```

### 2. Utiliser un CDN pour ip-api.com
```javascript
fetch('https://ip-api.com/json/', {...})
```

### 3. Ajouter de la mise en cache
```javascript
// Vérifier localStorage en premier
const cached = localStorage.getItem('geolocation_cache');
if (cached) {
    handleGeolocationSuccess(JSON.parse(cached));
    return;
}
```

### 4. Logs serveur
```php
// Dans GeoLocationService.php
\error_log('Géolocalisation IP: ' . $ipAddress);
```

---

## 📋 Checklist de débogage

- [ ] Ouvrir F12 → Console
- [ ] Chercher logs avec `🌍` ou `❌`
- [ ] Vérifier `public/js/geolocation.js` existe
- [ ] Vérifier `src/Controller/GeolocationApiController.php` existe
- [ ] Tester `http://127.0.0.1:8000/debug_geolocation.php`
- [ ] Tester `curl https://ip-api.com/json/`
- [ ] Tester `curl http://127.0.0.1:8000/api/geolocation/client`
- [ ] Vider cache navigateur (Ctrl+Shift+Del)
- [ ] Vider cache Symfony : `php bin/console cache:clear`
- [ ] Redémarrer le serveur : `php bin/console server:run`

---

## 🆘 Cas critiques

### Si tout échoue

1. **Ouvrir la console (F12)**
2. **Noter les erreurs exactes**
3. **Vérifier les logs serveur :**
   ```bash
   tail -f var/log/dev.log | grep -i geolocation
   ```

4. **Fallback final :** La commande est **toujours créée** même sans géolocalisation
   - IP : NULL
   - Country : NULL
   - Les informations de géolocalisation manqueront mais la commande fonctionnera

---

## 📞 Support

**Si le problème persiste :**

1. Vérifier les logs serveur
2. Vérifier que le service `GeoLocationService` est injecté correctement
3. Vérifier la base de données pour les colonnes manquantes
4. Exécuter la migration : `php bin/console doctrine:migrations:migrate`
5. Vider le cache : `php bin/console cache:clear`

