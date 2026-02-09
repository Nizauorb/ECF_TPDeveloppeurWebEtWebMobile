// frontend/js/order.js
// Gestion de la page de commande

// Données des menus (importées depuis menu.js via copie pour éviter les problèmes d'import ES module)
const orderMenuData = {
    classique: {
        title: "Menu Classique",
        price: 35,
        image: "Classique.png",
        description: "Un festin convivial et raffiné à partir de 2 convives.",
        minPeople: 2,
        regime: "Classique",
        stockDisponible: 12,
        conditionsCommande: "Commande à effectuer au minimum 3 jours avant la prestation. Conservation au réfrigérateur entre 0°C et 4°C.",
        allergenes: ["Gluten", "Lait et produits laitiers", "Œufs"]
    },
    noel: {
        title: "Menu de Noël",
        price: 55,
        image: "Noel.png",
        description: "Un festin de Noël généreux à partir de 6 convives.",
        minPeople: 6,
        regime: "Classique",
        stockDisponible: 5,
        conditionsCommande: "Commande à effectuer au minimum 2 semaines avant la prestation en raison de la disponibilité saisonnière des produits.",
        allergenes: ["Mollusques", "Lait et produits laitiers", "Fruits à coque", "Œufs", "Gluten"]
    },
    paques: {
        title: "Menu de Pâques",
        price: 38,
        image: "Paques.png",
        description: "Un menu de Pâques automnal et réconfortant à partir de 4 convives.",
        minPeople: 4,
        regime: "Classique",
        stockDisponible: 8,
        conditionsCommande: "Commande à effectuer au minimum 5 jours avant la prestation. Conservation au réfrigérateur entre 0°C et 4°C.",
        allergenes: ["Lait et produits laitiers", "Fruits à coque", "Gluten", "Œufs"]
    },
    event: {
        title: "Menu d'Événements",
        price: 48,
        image: "Event.png",
        description: "Un menu événementiel raffiné à partir de 10 convives.",
        minPeople: 10,
        regime: "Classique",
        stockDisponible: 3,
        conditionsCommande: "Commande à effectuer au minimum 3 semaines avant la prestation. Service traiteur sur place recommandé pour les groupes de plus de 15 personnes.",
        allergenes: ["Lait et produits laitiers", "Œufs", "Gluten"]
    }
};

// Ville du restaurant (pour calcul frais de livraison)
const VILLE_RESTAURANT = 'bordeaux';
const COORDS_RESTAURANT = { lat: 44.837789, lon: -0.57918 }; // Bordeaux centre
const FRAIS_LIVRAISON_BASE = 0; // Gratuit dans Bordeaux
const FRAIS_KM = 0.59; // 0.59€/km hors Bordeaux
const SEUIL_REDUCTION_PERSONNES = 5;
const REDUCTION_POURCENT = 10;

var currentUser = null;
var selectedMenu = null;
var csrfToken = null;
var distanceLivraison = 0; // Distance en km calculée
var deliveryFeeTimeout = null; // Debounce pour le calcul des frais

// Calcul de la distance à vol d'oiseau (formule de Haversine)
function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Rayon de la Terre en km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

// Calculer les frais de livraison via géocodage de l'adresse
async function calculateDeliveryFees() {
    const adresse = (document.getElementById('order-adresse')?.value || '').trim();
    const codePostal = (document.getElementById('order-code-postal')?.value || '').trim();
    const ville = (document.getElementById('order-ville')?.value || '').trim();
    
    if (!ville && !codePostal) {
        distanceLivraison = 0;
        updateRecap();
        return;
    }
    
    // Si la ville est Bordeaux → 0 km, frais gratuits
    if (ville.toLowerCase().replace(/[àâ]/g, 'a').replace(/[éèêë]/g, 'e') === 'bordeaux') {
        distanceLivraison = 0;
        updateRecap();
        return;
    }
    
    // Géocoder l'adresse via l'API adresse.data.gouv.fr
    try {
        const query = encodeURIComponent(`${adresse} ${codePostal} ${ville}`);
        const response = await fetch(`https://api-adresse.data.gouv.fr/search/?q=${query}&limit=1`);
        const data = await response.json();
        
        if (data.features && data.features.length > 0) {
            const [lon, lat] = data.features[0].geometry.coordinates;
            const distance = haversineDistance(COORDS_RESTAURANT.lat, COORDS_RESTAURANT.lon, lat, lon);
            // Appliquer un facteur 1.3 pour approximer la distance routière
            distanceLivraison = Math.round(distance * 1.3 * 100) / 100;
        } else {
            distanceLivraison = 0;
        }
    } catch (error) {
        console.error('Erreur géocodage:', error);
        distanceLivraison = 0;
    }
    
    updateRecap();
}

// Debounce du calcul des frais de livraison
function debouncedCalculateDeliveryFees() {
    if (deliveryFeeTimeout) clearTimeout(deliveryFeeTimeout);
    deliveryFeeTimeout = setTimeout(calculateDeliveryFees, 500);
}

// Calculer les frais de livraison à partir de la distance
function getDeliveryFees() {
    if (distanceLivraison <= 0) return FRAIS_LIVRAISON_BASE;
    return Math.round(distanceLivraison * FRAIS_KM * 100) / 100;
}

// Vérification de l'authentification
function requireAuth() {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
        return null;
    }
    
    try {
        return JSON.parse(userStr);
    } catch (e) {
        return null;
    }
}

// Charger le token CSRF
async function loadCSRFToken() {
    try {
        const response = await fetch(`${API_BASE_URL}/csrf/token.php`, {
            method: 'GET',
            credentials: 'include'
        });
        if (response.ok) {
            const data = await response.json();
            csrfToken = data.csrf_token;
        }
    } catch (error) {
        console.error('Erreur chargement CSRF token:', error);
    }
}

function getCsrfHeaders() {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    const token = localStorage.getItem('token');
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
}

// Initialisation
(function() {
    currentUser = requireAuth();
    
    if (!currentUser) {
        // Afficher l'alerte de connexion requise
        const authAlert = document.getElementById('order-auth-alert');
        if (authAlert) {
            authAlert.style.display = 'flex';
            authAlert.style.cssText = 'display: flex !important;';
        }
        // Désactiver le formulaire
        const form = document.getElementById('order-form');
        if (form) form.style.display = 'none';
        return;
    }
    
    // Charger le token CSRF
    loadCSRFToken();
    
    // Pré-remplir les informations personnelles
    fillUserInfo();
    
    // Remplir le select des menus
    populateMenuSelect();
    
    // Vérifier si un menu est pré-sélectionné (via URL)
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedMenu = urlParams.get('menu');
    if (preselectedMenu && orderMenuData[preselectedMenu]) {
        document.getElementById('order-menu').value = preselectedMenu;
        onMenuChange(preselectedMenu);
    }
    
    // Configurer la date minimum (aujourd'hui + délai selon le menu)
    setMinDate();
    
    // Écouteurs d'événements
    setupOrderListeners();
})();

// Pré-remplir les infos utilisateur
function fillUserInfo() {
    if (!currentUser) return;
    
    const fields = {
        'order-nom': currentUser.lastName || '',
        'order-prenom': currentUser.firstName || '',
        'order-email': currentUser.email || '',
        'order-telephone': currentUser.phone || '',
        'order-adresse': currentUser.address || ''
    };
    
    Object.entries(fields).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) el.value = value;
    });
}

// Remplir le select des menus
function populateMenuSelect() {
    const select = document.getElementById('order-menu');
    if (!select) return;
    
    Object.entries(orderMenuData).forEach(([key, menu]) => {
        if (menu.stockDisponible > 0) {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = `${menu.title} — ${menu.price}€/pers. (min. ${menu.minPeople} pers.)`;
            select.appendChild(option);
        }
    });
}

// Configurer la date minimum
function setMinDate() {
    const dateInput = document.getElementById('order-date');
    if (!dateInput) return;
    
    // Par défaut : demain
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];
}

// Mettre à jour la date minimum selon les conditions du menu
function updateMinDateForMenu(menuKey) {
    const dateInput = document.getElementById('order-date');
    if (!dateInput) return;
    
    let daysAhead = 1;
    
    // Extraire le délai minimum depuis les conditions
    if (menuKey === 'classique') daysAhead = 3;
    else if (menuKey === 'paques') daysAhead = 5;
    else if (menuKey === 'noel') daysAhead = 14;
    else if (menuKey === 'event') daysAhead = 21;
    
    const minDate = new Date();
    minDate.setDate(minDate.getDate() + daysAhead);
    dateInput.min = minDate.toISOString().split('T')[0];
    
    // Si la date actuelle est avant le minimum, la réinitialiser
    if (dateInput.value && new Date(dateInput.value) < minDate) {
        dateInput.value = '';
    }
}

// Quand un menu est sélectionné
function onMenuChange(menuKey) {
    const summary = document.getElementById('order-menu-summary');
    const nbInput = document.getElementById('order-nb-personnes');
    const infoText = document.getElementById('order-personnes-info');
    
    if (!menuKey || !orderMenuData[menuKey]) {
        selectedMenu = null;
        if (summary) summary.style.display = 'none';
        if (nbInput) { nbInput.value = ''; nbInput.min = 1; }
        updateRecap();
        return;
    }
    
    selectedMenu = { key: menuKey, ...orderMenuData[menuKey] };
    
    // Afficher le résumé du menu
    if (summary) {
        summary.style.display = 'block';
        document.getElementById('order-menu-image').src = selectedMenu.image;
        document.getElementById('order-menu-image').alt = selectedMenu.title;
        document.getElementById('order-menu-title').textContent = selectedMenu.title;
        document.getElementById('order-menu-description').textContent = selectedMenu.description;
        document.getElementById('order-menu-regime').textContent = selectedMenu.regime;
        document.getElementById('order-menu-price-badge').textContent = `${selectedMenu.price}€/personne`;
        document.getElementById('order-menu-conditions-text').textContent = selectedMenu.conditionsCommande;
        
        // Badge stock
        const stockBadge = document.getElementById('order-menu-stock');
        stockBadge.textContent = `${selectedMenu.stockDisponible} disponibles`;
        stockBadge.className = selectedMenu.stockDisponible >= 8 ? 'badge bg-success' : 
                               selectedMenu.stockDisponible >= 4 ? 'badge bg-warning' : 'badge bg-danger';
    }
    
    // Configurer le nombre de personnes
    if (nbInput) {
        nbInput.min = selectedMenu.minPeople;
        nbInput.removeAttribute('max');
        nbInput.value = selectedMenu.minPeople;
        nbInput.removeAttribute('readonly');
    }
    if (infoText) {
        infoText.textContent = `Minimum ${selectedMenu.minPeople} personnes — Réduction de 10% à partir de ${selectedMenu.minPeople + SEUIL_REDUCTION_PERSONNES} personnes`;
    }
    
    // Mettre à jour la date minimum
    updateMinDateForMenu(menuKey);
    
    // Mettre à jour le récapitulatif
    updateRecap();
}

// Calcul et mise à jour du récapitulatif
function updateRecap() {
    const recapDiv = document.getElementById('order-recap');
    const recapEmpty = document.getElementById('order-recap-empty');
    const submitBtn = document.getElementById('order-submit-btn');
    
    if (!selectedMenu) {
        if (recapDiv) recapDiv.style.display = 'none';
        if (recapEmpty) recapEmpty.style.display = 'block';
        if (submitBtn) submitBtn.disabled = true;
        return;
    }
    
    const nbPersonnes = parseInt(document.getElementById('order-nb-personnes').value) || selectedMenu.minPeople;
    const prixUnitaire = selectedMenu.price;
    const sousTotal = prixUnitaire * nbPersonnes;
    
    // Réduction 10% si nb personnes > minimum + 5
    let reductionPourcent = 0;
    let reductionMontant = 0;
    if (nbPersonnes >= selectedMenu.minPeople + SEUIL_REDUCTION_PERSONNES) {
        reductionPourcent = REDUCTION_POURCENT;
        reductionMontant = sousTotal * (REDUCTION_POURCENT / 100);
    }
    
    // Frais de livraison (calculés via géocodage)
    const fraisLivraison = getDeliveryFees();
    
    const totalAvantLivraison = sousTotal - reductionMontant;
    const total = totalAvantLivraison + fraisLivraison;
    
    // Afficher le récapitulatif
    if (recapDiv) recapDiv.style.display = 'block';
    if (recapEmpty) recapEmpty.style.display = 'none';
    
    document.getElementById('recap-menu-nom').textContent = selectedMenu.title;
    document.getElementById('recap-prix-unitaire').textContent = formatPrice(prixUnitaire);
    document.getElementById('recap-nb-personnes').textContent = `× ${nbPersonnes}`;
    document.getElementById('recap-sous-total').textContent = formatPrice(sousTotal);
    
    // Afficher les frais de livraison avec détail distance
    const fraisLivraisonEl = document.getElementById('recap-frais-livraison');
    if (distanceLivraison > 0) {
        fraisLivraisonEl.innerHTML = `${formatPrice(fraisLivraison)} <small class="text-muted">(${distanceLivraison.toFixed(1)} km × ${FRAIS_KM.toFixed(2)} €)</small>`;
    } else {
        fraisLivraisonEl.textContent = 'Gratuit (Bordeaux)';
    }
    
    document.getElementById('recap-total').textContent = formatPrice(total);
    
    // Réduction
    const reductionRow = document.getElementById('recap-reduction-row');
    if (reductionPourcent > 0) {
        reductionRow.style.display = '';
        document.getElementById('recap-reduction-pourcent').textContent = `(${reductionPourcent}%)`;
        document.getElementById('recap-reduction-montant').textContent = `- ${formatPrice(reductionMontant)}`;
    } else {
        reductionRow.style.display = 'none';
    }
    
    // Activer le bouton de soumission
    if (submitBtn) submitBtn.disabled = false;
}

// Formater un prix
function formatPrice(amount) {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(amount);
}

// Configurer les écouteurs d'événements
function setupOrderListeners() {
    // Changement de menu
    const menuSelect = document.getElementById('order-menu');
    if (menuSelect) {
        menuSelect.addEventListener('change', (e) => onMenuChange(e.target.value));
    }
    
    // Boutons +/- pour le nombre de personnes
    const btnMinus = document.getElementById('btn-minus-personnes');
    const btnPlus = document.getElementById('btn-plus-personnes');
    const nbInput = document.getElementById('order-nb-personnes');
    
    if (btnMinus && nbInput) {
        btnMinus.addEventListener('click', () => {
            const min = parseInt(nbInput.min) || 1;
            const current = parseInt(nbInput.value) || min;
            if (current > min) {
                nbInput.value = current - 1;
                updateRecap();
            }
        });
    }
    
    if (btnPlus && nbInput) {
        btnPlus.addEventListener('click', () => {
            const current = parseInt(nbInput.value) || 1;
            nbInput.value = current + 1;
            updateRecap();
        });
    }
    
    if (nbInput) {
        nbInput.addEventListener('change', () => updateRecap());
    }
    
    // Changement d'adresse/code postal/ville → recalculer les frais de livraison
    const adresseInput = document.getElementById('order-adresse');
    const codePostalInput = document.getElementById('order-code-postal');
    const villeInput = document.getElementById('order-ville');
    
    if (adresseInput) adresseInput.addEventListener('input', debouncedCalculateDeliveryFees);
    if (codePostalInput) codePostalInput.addEventListener('input', debouncedCalculateDeliveryFees);
    if (villeInput) villeInput.addEventListener('input', debouncedCalculateDeliveryFees);
    
    // Soumission du formulaire
    const form = document.getElementById('order-form');
    if (form) {
        form.addEventListener('submit', handleOrderSubmit);
    }
}

// Soumission de la commande
async function handleOrderSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('order-form');
    
    // Validation HTML5
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    // Validation personnalisée
    if (!selectedMenu) {
        showFeedback('Veuillez sélectionner un menu.', 'danger');
        return;
    }
    
    const nbPersonnes = parseInt(document.getElementById('order-nb-personnes').value);
    if (nbPersonnes < selectedMenu.minPeople) {
        showFeedback(`Le nombre minimum de personnes pour ce menu est ${selectedMenu.minPeople}.`, 'danger');
        return;
    }
    
    const adresse = document.getElementById('order-adresse').value.trim();
    const codePostal = document.getElementById('order-code-postal').value.trim();
    const ville = document.getElementById('order-ville').value.trim();
    const date = document.getElementById('order-date').value;
    const heure = document.getElementById('order-heure').value;
    
    if (!adresse || !codePostal || !ville || !date || !heure) {
        form.classList.add('was-validated');
        showFeedback('Veuillez remplir tous les champs obligatoires.', 'danger');
        return;
    }
    
    // Calcul du prix
    const prixUnitaire = selectedMenu.price;
    const sousTotal = prixUnitaire * nbPersonnes;
    let reductionPourcent = 0;
    let reductionMontant = 0;
    if (nbPersonnes >= selectedMenu.minPeople + SEUIL_REDUCTION_PERSONNES) {
        reductionPourcent = REDUCTION_POURCENT;
        reductionMontant = sousTotal * (REDUCTION_POURCENT / 100);
    }
    const fraisLivraison = getDeliveryFees();
    const total = sousTotal - reductionMontant + fraisLivraison;
    
    // Préparer les données
    const orderData = {
        user_id: currentUser.id,
        menu_key: selectedMenu.key,
        menu_nom: selectedMenu.title,
        prix_unitaire: prixUnitaire,
        nombre_personnes: nbPersonnes,
        nombre_personnes_min: selectedMenu.minPeople,
        adresse_livraison: adresse,
        code_postal_livraison: codePostal,
        ville_livraison: ville,
        distance_km: distanceLivraison > 0 ? distanceLivraison : null,
        date_prestation: date,
        heure_prestation: heure,
        frais_livraison: fraisLivraison,
        sous_total: sousTotal,
        reduction_pourcent: reductionPourcent,
        reduction_montant: reductionMontant,
        total: total,
        notes: document.getElementById('order-notes').value.trim() || null
    };
    
    // Désactiver le bouton
    const submitBtn = document.getElementById('order-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Envoi en cours...';
    
    try {
        const response = await fetch(`${API_BASE_URL}/commands/create.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Succès : afficher le message et rediriger
            showFeedback(
                `<div class="text-center">
                    <i class="bi bi-check-circle-fill fs-1 text-success d-block mb-3"></i>
                    <h4 class="fw-bold">Commande confirmée !</h4>
                    <p>Votre commande <strong>#${result.order_id}</strong> a été enregistrée avec succès.</p>
                    <p class="text-muted">Un email de confirmation vous a été envoyé à <strong>${currentUser.email}</strong>.</p>
                    <a href="/UserDashboard" class="btn btn-primary mt-2">
                        <i class="bi bi-bag-check me-1"></i>Voir mes commandes
                    </a>
                </div>`,
                'success'
            );
            // Masquer le formulaire
            document.getElementById('order-form').style.display = 'none';
        } else {
            showFeedback(result.message || 'Erreur lors de la création de la commande.', 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Valider ma commande';
        }
    } catch (error) {
        console.error('Erreur:', error);
        showFeedback('Erreur de communication avec le serveur.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Valider ma commande';
    }
}

// Afficher un message de feedback
function showFeedback(message, type) {
    const feedback = document.getElementById('order-feedback');
    if (!feedback) return;
    
    feedback.style.display = 'block';
    feedback.className = `alert alert-${type} mb-5`;
    feedback.innerHTML = message;
    
    // Scroller vers le feedback
    feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
