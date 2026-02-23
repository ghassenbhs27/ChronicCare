/**
 * Géolocalisation automatique via API Symfony (serveur)
 * Le serveur appelle IP-API sans problème CORS
 * Le navigateur appelle SEULEMENT l'APISymfony
 */

// Configuration
const GEOLOCATION_TIMEOUT = 5000; // 5 secondes de timeout
const GEOLOCATION_IP_INPUT = '.geolocation-ip';
const GEOLOCATION_INFO_CONTAINER = '#geolocation-info';
const GEOLOCATION_LOADING_DIV = '#geolocation-loading';

console.log('🌍 Script de géolocalisation chargé');

// Attendre que le DOM soit prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGeolocation);
} else {
    // DOM est déjà chargé
    initGeolocation();
}

/**
 * Initialise la géolocalisation
 */
function initGeolocation() {
    console.log('🌍 Initialisation de la géolocalisation...');
    
    const ipInput = document.querySelector(GEOLOCATION_IP_INPUT);
    
    if (!ipInput) {
        console.warn('⚠️ Champ de géolocalisation \'.geolocation-ip\' non trouvé');
        return;
    }
    
    console.log('✅ Champ trouvé, démarrage de la détection...');
    
    // Démarrer la détection de géolocalisation
    fetchServerGeolocation();
}

/**
 * Appelle l'API serveur Symfony pour la géolocalisation
 * C'est le SERVEUR qui appelle IP-API, pas le navigateur
 * Pas de problème CORS cette façon !
 */
function fetchServerGeolocation() {
    console.log('📡 Appel de /api/geolocation/client (serveur Symfony appelle IP-API)');
    
    // Créer un timeout avec Promise.race
    const timeoutPromise = new Promise((_, reject) =>
        setTimeout(() => reject(new Error('Timeout serveur (' + GEOLOCATION_TIMEOUT + 'ms)')), GEOLOCATION_TIMEOUT)
    );
    
    const fetchPromise = fetch('/api/geolocation/client', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    });
    
    return Promise.race([fetchPromise, timeoutPromise])
        .then(response => {
            console.log('📡 Réponse serveur reçue:', response.status);
            if (!response.ok) {
                throw new Error('Serveur erreur HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('📦 Données géolocalisation reçues:', data);
            
            if (data && data.status === 'success' && data.data) {
                console.log('✅ Géolocalisation réussie:', data.data.city, data.data.country);
                
                // Extraire l'IP de la réponse
                const ipFromServer = data.ip || data.data.ip;
                
                handleGeolocationSuccess({
                    ip: ipFromServer,
                    country: data.data.country,
                    city: data.data.city,
                    region: data.data.region,
                    latitude: data.data.latitude,
                    longitude: data.data.longitude,
                    timezone: data.data.timezone,
                    isp: data.data.isp
                });
            } else {
                throw new Error('Réponse invalide: ' + (data.message || 'Pas de données'));
            }
        })
        .catch(error => {
            console.error('❌ Erreur géolocalisation (affichage à l\'utilisateur):', error.message);
            showGeolocationError('Impossible de détecter votre localisation. ' + error.message);
        });
}

/**
 * Gère la réussite de la géolocalisation
 */
function handleGeolocationSuccess(data) {
    console.log('🎉 Traitement des données de géolocalisation...');
    
    // Ajouter l'IP au formulaire caché
    const ipInput = document.querySelector(GEOLOCATION_IP_INPUT);
    if (ipInput && data.ip) {
        ipInput.value = data.ip;
        console.log('✅ IP définie dans le formulaire:', data.ip);
    } else {
        console.warn('⚠️ Impossible de définir l\'IP (champ ou IP manquante)');
    }
    
    // Afficher les informations
    displayGeolocationInfo(data);
    
    // Masquer le message de chargement
    hideLoadingMessage();
}

/**
 * Affiche les informations de géolocalisation formatées
 */
function displayGeolocationInfo(data) {
    const infoContainer = document.querySelector(GEOLOCATION_INFO_CONTAINER);
    
    if (!infoContainer) {
        console.warn('⚠️ Conteneur #geolocation-info non trouvé');
        return;
    }
    
    const city = data.city || 'N/A';
    const region = data.region ? data.region + ', ' : '';
    const country = data.country || 'N/A';
    const lat = (typeof data.latitude === 'number') ? data.latitude.toFixed(4) : '--';
    const lon = (typeof data.longitude === 'number') ? data.longitude.toFixed(4) : '--';
    const timezone = data.timezone || 'N/A';
    const isp = data.isp || 'N/A';
    
    infoContainer.innerHTML = `
        <div class="alert alert-success d-flex align-items-center gap-3 border-start border-success border-4">
            <div class="flex-shrink-0">
                <i class="bi bi-geo-alt-fill fs-4" style="color: var(--bs-success);"></i>
            </div>
            <div class="flex-grow-1">
                <strong>✓ Localisation détectée</strong><br>
                <small class="text-muted">
                    <span class="badge bg-success" style="margin-top: 5px; display: inline-block;">
                        📍 ${city}, ${region}${country}
                    </span>
                    <br>
                    <span class="small text-muted" style="margin-top: 8px; display: block;">
                        <strong>Coordonnées:</strong> ${lat}°N, ${lon}°E<br>
                        <strong>Fuseau horaire:</strong> ${timezone}<br>
                        <strong>Opérateur:</strong> ${isp}
                    </span>
                </small>
            </div>
        </div>
    `;
    
    console.log('📍 Informations affichées avec succès');
}

/**
 * Affiche un message d'erreur de géolocalisation
 */
function showGeolocationError(errorMessage) {
    const loadingDiv = document.querySelector(GEOLOCATION_LOADING_DIV);
    
    if (loadingDiv) {
        loadingDiv.className = 'alert alert-warning d-flex align-items-center gap-3 border-start border-warning border-4';
        loadingDiv.innerHTML = `
            <div class="flex-shrink-0">
                <i class="bi bi-exclamation-triangle-fill fs-4" style="color: var(--bs-warning);"></i>
            </div>
            <div class="flex-grow-1">
                <strong>⚠️ ${errorMessage}</strong><br>
                <small class="text-muted">
                    La détection de localisation n'a pas pu être réalisée. 
                    Votre commande sera tout de même traitée normalement.
                </small>
            </div>
        `;
    }
    
    console.warn('⚠️ Erreur de géolocalisation:', errorMessage);
}

/**
 * Cache le message de chargement
 */
function hideLoadingMessage() {
    const loadingDiv = document.querySelector(GEOLOCATION_LOADING_DIV);
    if (loadingDiv) {
        loadingDiv.style.display = 'none';
        console.log('✅ Message de chargement masqué');
    }
}

/**
 * Fonction de validation pour le formulaire
 */
window.validateCheckoutForm = function(formElement) {
    const ipInput = document.querySelector(GEOLOCATION_IP_INPUT);
    if (!ipInput || !ipInput.value) {
        console.warn('⚠️ Avertissement: IP non détectée');
    } else {
        console.log('✅ IP détectée et prête:', ipInput.value);
    }
    return true;
};

