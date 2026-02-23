<?php
/**
 * Fichier de débogage pour la géolocalisation
 * 
 * Placez ce fichier dans le répertoire racine et appelez-le pour déboguer les problèmes de géolocalisation
 * Test manuelle: http://127.0.0.1:8000/debug_geolocation.php
 */

?>
<!DOCTYPE html>
<html>
<head>
    <title>🔧 Débogage Géolocalisation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .test-card { margin-bottom: 20px; }
        .test-result { margin-top: 10px; padding: 10px; border-radius: 4px; }
        .test-success { background: #d4edda; color: #155724; }
        .test-error { background: #f8d7da; color: #721c24; }
        .test-pending { background: #fff3cd; color: #856404; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Débogage Géolocalisation</h1>
        <p class="text-muted">Cette page teste les différentes sources de géolocalisation</p>

        <div class="row">
            <!-- Test IP-API -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">📡 Test IP-API</h5>
                    </div>
                    <div class="card-body">
                        <p>Test direct de l'API <code>ip-api.com</code></p>
                        <button class="btn btn-primary btn-sm" onclick="testIpApi()">Tester IP-API</button>
                        <div id="ipapi-result" class="test-result" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Test API Serveur -->
            <div class="col-md-6">
                <div class="card test-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🖥️ Test API Serveur</h5>
                    </div>
                    <div class="card-body">
                        <p>Test de <code>/api/geolocation/client</code></p>
                        <button class="btn btn-success btn-sm" onclick="testServerApi()">Tester API Serveur</button>
                        <div id="server-result" class="test-result" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test du script de géolocalisation -->
        <div class="card test-card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">🌍 Test du Script Complète</h5>
            </div>
            <div class="card-body">
                <p>Test du script de géolocalisation complet</p>
                <button class="btn btn-info btn-sm" onclick="testFullScript()">Tester le Script</button>
                <div id="script-result" class="test-result" style="display:none;"></div>
                
                <hr>
                
                <h6>Console de débogage :</h6>
                <div id="debug-console" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto;">
                    <p style="color: #888;">En attente de tests...</p>
                </div>
            </div>
        </div>

        <!-- Informations du client -->
        <div class="card test-card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">ℹ️ Informations Client</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>User Agent</th>
                        <td><code id="ua"></code></td>
                    </tr>
                    <tr>
                        <th>Langue</th>
                        <td><code id="lang"></code></td>
                    </tr>
                    <tr>
                        <th>Fuseau horaire</th>
                        <td><code id="tz"></code></td>
                    </tr>
                    <tr>
                        <th>Résolution</th>
                        <td><code id="res"></code></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Remplir les infos client
        document.getElementById('ua').textContent = navigator.userAgent;
        document.getElementById('lang').textContent = navigator.language;
        document.getElementById('tz').textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;
        document.getElementById('res').textContent = window.innerWidth + ' x ' + window.innerHeight;

        const debugConsole = document.getElementById('debug-console');

        function log(message) {
            const time = new Date().toLocaleTimeString('fr-FR');
            const line = document.createElement('div');
            line.textContent = `[${time}] ${message}`;
            debugConsole.appendChild(line);
            debugConsole.scrollTop = debugConsole.scrollHeight;
        }

        async function testIpApi() {
            const resultDiv = document.getElementById('ipapi-result');
            resultDiv.className = 'test-result test-pending';
            resultDiv.textContent = '⏳ Test en cours...';
            resultDiv.style.display = 'block';
            log('🔄 Test IP-API en cours...');

            try {
                const response = await fetch('https://ip-api.com/json/', {
                    headers: { 'Accept': 'application/json' },
                    mode: 'cors'
                });
                const data = await response.json();

                if (data.status === 'success') {
                    log('✅ IP-API OK: ' + data.city + ', ' + data.country);
                    resultDiv.className = 'test-result test-success';
                    resultDiv.innerHTML = `
                        ✅ <strong>IP-API fonctionne</strong><br>
                        📍 ${data.city}, ${data.regionName}, ${data.country}<br>
                        📡 IP: ${data.query}<br>
                        🕐 Fuseau: ${data.timezone}<br>
                        🔌 Opérateur: ${data.isp}
                    `;
                } else {
                    throw new Error('IP-API erreur: ' + data.message);
                }
            } catch (error) {
                log('❌ IP-API ERREUR: ' + error.message);
                resultDiv.className = 'test-result test-error';
                resultDiv.textContent = '❌ IP-API échoué: ' + error.message;
            }
        }

        async function testServerApi() {
            const resultDiv = document.getElementById('server-result');
            resultDiv.className = 'test-result test-pending';
            resultDiv.textContent = '⏳ Test en cours...';
            resultDiv.style.display = 'block';
            log('🔄 Test API Serveur en cours...');

            try {
                const response = await fetch('/api/geolocation/client', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (data.status === 'success' && data.data) {
                    log('✅ API Serveur OK: ' + data.data.city + ', ' + data.data.country);
                    resultDiv.className = 'test-result test-success';
                    resultDiv.innerHTML = `
                        ✅ <strong>API Serveur fonctionne</strong><br>
                        📍 ${data.data.city}, ${data.data.region}, ${data.data.country}<br>
                        📡 IP: ${data.ip}<br>
                        🕐 Fuseau: ${data.data.timezone}<br>
                        🔌 Opérateur: ${data.data.isp}
                    `;
                } else {
                    throw new Error('Serveur erreur: ' + (data.message || 'Unknown'));
                }
            } catch (error) {
                log('❌ API Serveur ERREUR: ' + error.message);
                resultDiv.className = 'test-result test-error';
                resultDiv.textContent = '❌ API Serveur échouée: ' + error.message;
            }
        }

        function testFullScript() {
            const resultDiv = document.getElementById('script-result');
            resultDiv.style.display = 'block';
            log('🌍 Test du script complet en cours...');
            
            // Charger le script
            const script = document.createElement('script');
            script.src = '/js/geolocation.js?t=' + Date.now();
            script.onload = () => {
                log('✅ Script chargé avec succès');
                resultDiv.className = 'test-result test-success';
                resultDiv.innerHTML = `
                    ✅ <strong>Script chargé</strong><br>
                    Vérifiez la console F12 pour plus de détails<br>
                    <small>Les messages de géolocalisation apparaîtront ici</small>
                `;
            };
            script.onerror = () => {
                log('❌ ERREUR: Script de géolocalisation non trouvé');
                resultDiv.className = 'test-result test-error';
                resultDiv.textContent = '❌ Erreur lors du chargement du script';
            };
            document.head.appendChild(script);
        }

        log('✅ Débogage initialisé');
    </script>
</body>
</html>
