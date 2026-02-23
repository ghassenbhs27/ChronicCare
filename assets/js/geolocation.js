/**
 * Géolocalisation automatique via IP-API
 * Collecte les données de géolocalisation du client et les ajoute au formulaire
 */

document.addEventListener('DOMContentLoaded', function() {
    const ipAddressInput = document.querySelector('.geolocation-ip');
    
    if (!ipAddressInput) {
        console.warn('Champ de géolocalisation non trouvé');
        return;
    }

    // Récupérer l'IP du client et la géolocalisation
    fetchGeolocation();
});

/**
 * Récupère les données de géolocalisation via ip-api
 */
function fetchGeolocation() {
    // Appel à ip-api (gratuit, 45 requêtes/min)
    fetch('https://ip-api.com/json/', {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erreur lors de la récupération de la géolocalisation');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            // Ajouter l'IP au formulaire caché
            const ipInput = document.querySelector('.geolocation-ip');
            if (ipInput) {
                ipInput.value = data.query;
            }

            // Afficher les informations de géolocalisation
            displayGeolocationInfo(data);
        } else {
            console.error('Erreur de géolocalisation:', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur lors de la géolocalisation:', error);
    });
}

/**
 * Affiche les informations de géolocalisation au client
 */
function displayGeolocationInfo(data) {
    // Créer un conteneur pour afficher les infos si nécessaire
    const geolocationInfoContainer = document.getElementById('geolocation-info');
    
    if (geolocationInfoContainer) {
        geolocationInfoContainer.innerHTML = `
            <div class="alert alert-info mt-3">
                <strong>📍 Géolocalisation détectée :</strong><br>
                <small>
                    <strong>Adresse:</strong> ${data.city || 'N/A'}, ${data.regionName || ''}, ${data.country || 'N/A'}<br>
                    <strong>Coordonnées:</strong> ${data.lat}°, ${data.lon}°<br>
                    <strong>Fuseau horaire:</strong> ${data.timezone}<br>
                    <strong>FAI:</strong> ${data.isp}
                </small>
            </div>
        `;
    }
}

/**
 * Valide le formulaire avant soumission et s'assure que l'IP est définie
 */
function validateCheckoutForm(formElement) {
    const ipInput = document.querySelector('.geolocation-ip');
    
    if (!ipInput || !ipInput.value) {
        console.error('IP non définie - attente de la géolocalisation');
        alert('La géolocalisation est en cours... Veuillez réessayer dans un instant.');
        return false;
    }
    
    return true;
}
