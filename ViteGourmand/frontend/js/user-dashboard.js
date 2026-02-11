// frontend/js/user-dashboard.js
function requireAuth() {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
        window.location.href = '/Login';
        return false;
    }
    
    try {
        return JSON.parse(userStr);
    } catch (e) {
        console.error('Erreur parsing user:', e);
        window.location.href = '/Login';
        return false;
    }
}

// Variables globales (var pour permettre le rechargement du script par le Router)
var currentUser = null;
var currentEditingOrder = null;
var csrfToken = null;
var loadedCommands = [];
var editDistanceLivraison = 0;
var editDeliveryFeeTimeout = null;

// Constantes livraison (identiques à order.js)
const COORDS_RESTAURANT = { lat: 44.837789, lon: -0.57918 };
const FRAIS_LIVRAISON_BASE = 5;
const FRAIS_KM = 0.59;

function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

async function calculateEditDeliveryFees() {
    const adresse = (document.getElementById('edit-adresse')?.value || '').trim();
    const codePostal = (document.getElementById('edit-code-postal')?.value || '').trim();
    const ville = (document.getElementById('edit-ville')?.value || '').trim();

    if (!ville && !codePostal) {
        editDistanceLivraison = 0;
        return;
    }

    if (ville.toLowerCase().replace(/[àâ]/g, 'a').replace(/[éèêë]/g, 'e') === 'bordeaux') {
        editDistanceLivraison = 0;
        return;
    }

    try {
        const query = encodeURIComponent(`${adresse} ${codePostal} ${ville}`);
        const response = await fetch(`https://api-adresse.data.gouv.fr/search/?q=${query}&limit=1`);
        const data = await response.json();
        if (data.features && data.features.length > 0) {
            const [lon, lat] = data.features[0].geometry.coordinates;
            const distance = haversineDistance(COORDS_RESTAURANT.lat, COORDS_RESTAURANT.lon, lat, lon);
            editDistanceLivraison = Math.round(distance * 1.3 * 100) / 100;
        } else {
            editDistanceLivraison = 0;
        }
    } catch (error) {
        console.error('[EditDeliveryFees] Erreur géocodage:', error);
        editDistanceLivraison = 0;
    }
}

function getEditDeliveryFees() {
    if (editDistanceLivraison <= 0) return FRAIS_LIVRAISON_BASE;
    return Math.round((FRAIS_LIVRAISON_BASE + editDistanceLivraison * FRAIS_KM) * 100) / 100;
}

function debouncedEditDeliveryFees(command) {
    if (editDeliveryFeeTimeout) clearTimeout(editDeliveryFeeTimeout);
    editDeliveryFeeTimeout = setTimeout(async () => {
        await calculateEditDeliveryFees();
        updateEditRecap(command);
    }, 500);
}

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

// Initialisation (exécution immédiate car le script est injecté dynamiquement par le Router)
(function() {
    currentUser = requireAuth();
    if (currentUser) {
        console.log('Utilisateur connecté:', currentUser);
        
        // Charger le token CSRF
        loadCSRFToken();
        
        // Afficher les infos utilisateur dans la sidebar
        displayUserInfo(currentUser);
        
        // Lire le paramètre ?section= pour afficher la bonne section
        const urlParams = new URLSearchParams(window.location.search);
        const section = urlParams.get('section');
        
        if (section === 'profile') {
            showProfile();
        } else if (section === 'review') {
            // Ouvrir le formulaire d'avis pour une commande spécifique (lien depuis le mail)
            const reviewOrderId = urlParams.get('order');
            loadUserCommands(currentUser.id).then(() => {
                if (reviewOrderId) {
                    openReviewModal(parseInt(reviewOrderId));
                }
            });
        } else {
            // Par défaut : afficher les commandes
            loadUserCommands(currentUser.id);
        }
        
        // Mettre en place les écouteurs d'événements
        setupEventListeners();
    }
})();

// Configuration des écouteurs d'événements
function setupEventListeners() {
    // Formulaire de profil
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', handleProfileSubmit);
    }
    
    // Confirmation de suppression de compte
    const deleteConfirmation = document.getElementById('delete-confirmation');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    if (deleteConfirmation && confirmDeleteBtn) {
        deleteConfirmation.addEventListener('input', (e) => {
            confirmDeleteBtn.disabled = e.target.value !== 'SUPPRIMER';
        });
    }
}

// Affichage des informations utilisateur
function displayUserInfo(user) {
    const fullName = `${user.firstName || ''} ${user.lastName || ''}`.trim();
    
    // Sidebar desktop
    const sidebarUserName = document.getElementById('sidebar-user-name');
    if (sidebarUserName) {
        sidebarUserName.textContent = fullName;
    }
    
    // Mobile header
    const sidebarUserNameMobile = document.getElementById('sidebar-user-name-mobile');
    if (sidebarUserNameMobile) {
        sidebarUserNameMobile.textContent = fullName;
    }
    
    // Header mobile (legacy)
    const headerUserName = document.getElementById('header-user-name');
    const mobileUserName = document.getElementById('mobile-user-name');
    const mobileUserEmail = document.getElementById('mobile-user-email');
    
    if (headerUserName) {
        headerUserName.textContent = fullName;
    }
    if (mobileUserName) {
        mobileUserName.textContent = fullName;
    }
    if (mobileUserEmail) {
        mobileUserEmail.textContent = user.email;
    }
    
    // Formulaire de profil
    document.getElementById('profile-nom').value = user.lastName || '';
    document.getElementById('profile-prenom').value = user.firstName || '';
    document.getElementById('profile-email').value = user.email || '';
    document.getElementById('profile-telephone').value = user.phone || '';
    document.getElementById('profile-adresse').value = user.adresse || '';
    document.getElementById('profile-code-postal').value = user.code_postal || '';
    document.getElementById('profile-ville').value = user.ville || '';
}

// Navigation
function showMyOrders() {
    // Masquer la section profil
    document.getElementById('profile-section').style.display = 'none';
    
    // Afficher la section commandes
    document.getElementById('orders-section').style.display = 'block';
    
    // Afficher les boutons d'action
    document.getElementById('dashboard-header-actions').classList.remove('d-none');
    
    // Mettre à jour le titre
    document.getElementById('page-title').textContent = 'Mes Commandes';
    
    // Mettre à jour la navigation active
    updateActiveNav('orders');
    
    // Recharger les commandes
    loadUserCommands(currentUser.id);
}

function showProfile() {
    // Masquer la section commandes
    document.getElementById('orders-section').style.display = 'none';
    
    // Afficher la section profil
    document.getElementById('profile-section').style.display = 'block';
    
    // Masquer les boutons d'action (Actualiser / Nouvelle Commande)
    document.getElementById('dashboard-header-actions').classList.add('d-none');
    
    // Mettre à jour le titre
    document.getElementById('page-title').textContent = 'Mon Profil';
    
    // Mettre à jour la navigation active
    updateActiveNav('profile');
}

function updateActiveNav(section) {
    // Retirer la classe active de tous les nav-items du dashboard (sidebar + offcanvas)
    document.querySelectorAll('.dashboard-sidebar-nav .nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Ajouter la classe active aux éléments correspondants (index 1 = commandes, 2 = profil)
    const navItems = document.querySelectorAll('.dashboard-sidebar-nav .nav-item');
    navItems.forEach((item, index) => {
        // index 0 = Accueil, 1 = Commandes, 2 = Profil (répété pour chaque nav)
        const posInNav = index % 3;
        if (section === 'orders' && posInNav === 1) {
            item.classList.add('active');
        } else if (section === 'profile' && posInNav === 2) {
            item.classList.add('active');
        }
    });
}

// Fermer le menu offcanvas du dashboard (mobile)
function closeDashboardMenu() {
    const offcanvasEl = document.getElementById('dashboardMobileMenu');
    if (offcanvasEl) {
        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (offcanvas) {
            offcanvas.hide();
        }
    }
}

// Chargement des commandes utilisateur
async function loadUserCommands(userId) {
    try {
        // Afficher l'état de chargement
        document.getElementById('loading-state').style.display = 'block';
        document.getElementById('empty-state').style.display = 'none';
        document.getElementById('orders-list').style.display = 'none';
        
        const token = localStorage.getItem('token');
        const response = await fetch(`${API_BASE_URL}/commands/user-commands.php?user_id=${userId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const result = await response.json();
        
        // Masquer l'état de chargement
        document.getElementById('loading-state').style.display = 'none';
        
        if (result.success && result.data) {
            displayOrders(result.data);
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Erreur chargement commandes:', error);
        document.getElementById('loading-state').style.display = 'none';
        showEmptyState();
    }
}

// Affichage des commandes
function displayOrders(commands) {
    const ordersList = document.getElementById('orders-list');
    
    if (!commands || commands.length === 0) {
        showEmptyState();
        return;
    }
    
    // Stocker les commandes pour accès local
    loadedCommands = commands;
    
    // Vider la liste
    ordersList.innerHTML = '';
    
    // Ajouter chaque commande
    commands.forEach(command => {
        const orderElement = createOrderElement(command);
        ordersList.appendChild(orderElement);
    });
    
    // Afficher la liste
    ordersList.style.display = 'block';
}

function showEmptyState() {
    document.getElementById('empty-state').style.display = 'block';
    document.getElementById('orders-list').style.display = 'none';
}

// Création d'un élément de commande
function createOrderElement(command) {
    const orderDiv = document.createElement('div');
    orderDiv.className = 'order-item';
    
    // Formater la date
    const date = new Date(command.date_commande);
    const formattedDate = date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    
    // Déterminer le statut
    const statusClass = getStatusClass(command.statut);
    const statusText = getStatusText(command.statut);
    
    // Vérifier si la commande peut être modifiée/annulée
    const canModify = canModifyOrder(command.statut);
    
    // Formater la date de prestation
    const datePrestation = command.date_prestation ? new Date(command.date_prestation + 'T00:00:00').toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'long', year: 'numeric'
    }) : '';
    
    orderDiv.innerHTML = `
        <div class="order-header" onclick="toggleOrderDetails(${command.id})">
            <div class="order-info">
                <span class="order-ref">#${command.id}</span>
                <span class="order-date">${formattedDate}</span>
                <span class="order-price">${command.total ? command.total.toFixed(2) + ' €' : 'N/A'}</span>
            </div>
            <span class="order-status ${statusClass}">${statusText}</span>
        </div>
        <div class="order-details" id="order-details-${command.id}">
            <div class="order-content">
                <div class="detail-row">
                    <span class="detail-label">Menu :</span>
                    <span class="detail-value">${command.menu_nom || 'Menu inconnu'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Personnes :</span>
                    <span class="detail-value">${command.nombre_personnes || '-'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Prix unitaire :</span>
                    <span class="detail-value">${command.prix_unitaire ? command.prix_unitaire.toFixed(2) + ' €/pers.' : '-'}</span>
                </div>
                ${command.reduction_pourcent > 0 ? `
                    <div class="detail-row">
                        <span class="detail-label">Réduction :</span>
                        <span class="detail-value text-success">-${command.reduction_montant.toFixed(2)} € (${command.reduction_pourcent}%)</span>
                    </div>
                ` : ''}
                <div class="detail-row">
                    <span class="detail-label">Frais livraison :</span>
                    <span class="detail-value">${command.frais_livraison ? command.frais_livraison.toFixed(2) + ' €' : '-'}</span>
                </div>
                ${command.date_prestation ? `
                    <div class="detail-row">
                        <span class="detail-label">Prestation :</span>
                        <span class="detail-value">${datePrestation} à ${command.heure_prestation || ''}</span>
                    </div>
                ` : ''}
                ${command.adresse_livraison ? `
                    <div class="detail-row">
                        <span class="detail-label">Livraison :</span>
                        <span class="detail-value">${command.adresse_livraison}, ${command.code_postal_livraison || ''} ${command.ville_livraison || ''}</span>
                    </div>
                ` : ''}
                ${command.notes ? `
                    <div class="detail-row">
                        <span class="detail-label">Notes :</span>
                        <span class="detail-value">${command.notes}</span>
                    </div>
                ` : ''}
            </div>
            
            ${command.statut !== 'en_attente' && command.statut !== 'annulee' ? `
                <div class="order-tracking">
                    <h4>Suivi de la commande</h4>
                    ${generateOrderTracking(command.statut)}
                </div>
            ` : ''}
            
            <div class="order-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="showOrderDetailsModal(${command.id})">
                    <i class="bi bi-eye me-1"></i>
                    Voir détails
                </button>
                ${canModify ? `
                    <button class="btn btn-sm btn-outline-warning" onclick="editOrder(${command.id})">
                        <i class="bi bi-pencil me-1"></i>
                        Modifier
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelOrder(${command.id})">
                        <i class="bi bi-x-circle me-1"></i>
                        Annuler
                    </button>
                ` : ''}
                ${command.statut === 'terminee' ? `
                    <button class="btn btn-sm btn-outline-warning" onclick="openReviewModal(${command.id})">
                        <i class="bi bi-star me-1"></i>
                        Laisser un avis
                    </button>
                    <button class="btn btn-sm btn-success" onclick="reorderCommand(${command.id})">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Commander à nouveau
                    </button>
                ` : ''}
            </div>
        </div>
    `;
    
    return orderDiv;
}

// Toggle des détails de commande
function toggleOrderDetails(orderId) {
    const details = document.getElementById(`order-details-${orderId}`);
    if (details) {
        details.classList.toggle('show');
    }
}

// Génération du suivi de commande
function generateOrderTracking(status) {
    const steps = [
        { id: 'en_attente', title: 'Commande reçue', icon: 'bi-clock' },
        { id: 'acceptee', title: 'Acceptée', icon: 'bi-check-circle' },
        { id: 'en_preparation', title: 'En préparation', icon: 'bi-fire' },
        { id: 'en_livraison', title: 'En livraison', icon: 'bi-truck' },
        { id: 'livree', title: 'Livrée', icon: 'bi-check2-all' },
        { id: 'attente_retour_materiel', title: 'Retour matériel', icon: 'bi-box-seam' },
        { id: 'terminee', title: 'Terminée', icon: 'bi-check-circle-fill' }
    ];
    
    // Trouver l'index du statut courant
    const currentIndex = steps.findIndex(s => s.id === status);
    
    let html = '';
    
    steps.forEach((step, index) => {
        let stepClass = 'pending';
        if (index < currentIndex) {
            stepClass = 'completed';
        } else if (index === currentIndex) {
            stepClass = 'current';
        }
        
        html += `
            <div class="tracking-step">
                <div class="step-icon ${stepClass}">
                    <i class="bi ${step.icon}"></i>
                </div>
                <div class="step-text">
                    <div class="step-title">${step.title}</div>
                    ${stepClass === 'completed' ? '<div class="step-time">Terminé</div>' : ''}
                </div>
            </div>
        `;
    });
    
    return html;
}

// Fonctions utilitaires pour le statut
function getStatusClass(status) {
    const statusMap = {
        'en_attente': 'bg-warning text-dark',
        'acceptee': 'bg-info text-dark',
        'en_preparation': 'bg-info text-white',
        'en_livraison': 'bg-primary text-white',
        'livree': 'bg-success text-white',
        'attente_retour_materiel': 'bg-secondary text-white',
        'terminee': 'bg-success text-white',
        'annulee': 'bg-danger text-white'
    };
    return statusMap[status] || 'bg-warning text-dark';
}

function getStatusText(status) {
    const statusMap = {
        'en_attente': 'En attente',
        'acceptee': 'Acceptée',
        'en_preparation': 'En préparation',
        'en_livraison': 'En livraison',
        'livree': 'Livrée',
        'attente_retour_materiel': 'Retour matériel',
        'terminee': 'Terminée',
        'annulee': 'Annulée'
    };
    return statusMap[status] || 'Inconnu';
}

function canModifyOrder(status) {
    return status === 'en_attente';
}

// Gestion du profil
function enableProfileEdit() {
    const formControls = document.querySelectorAll('#profile-form .form-control');
    const profileActions = document.getElementById('profile-actions');
    
    formControls.forEach(control => {
        control.disabled = false;
    });
    
    profileActions.style.display = 'flex';
}

function cancelProfileEdit() {
    const formControls = document.querySelectorAll('#profile-form .form-control');
    const profileActions = document.getElementById('profile-actions');
    
    formControls.forEach(control => {
        control.disabled = true;
    });
    
    profileActions.style.display = 'none';
    
    // Restaurer les valeurs originales
    displayUserInfo(currentUser);
}

async function handleProfileSubmit(e) {
    e.preventDefault();
    
    const formData = {
        user_id: currentUser.id,
        last_name: document.getElementById('profile-nom').value,
        first_name: document.getElementById('profile-prenom').value,
        email: document.getElementById('profile-email').value,
        phone: document.getElementById('profile-telephone').value,
        adresse: document.getElementById('profile-adresse').value,
        code_postal: document.getElementById('profile-code-postal').value,
        ville: document.getElementById('profile-ville').value
    };
    
    try {
        const response = await fetch(`${API_BASE_URL}/user/update-profile.php`, {
            method: 'PUT',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mettre à jour les champs (sauf email si changement en attente)
            currentUser.firstName = formData.first_name;
            currentUser.lastName = formData.last_name;
            currentUser.phone = formData.phone;
            currentUser.adresse = formData.adresse;
            currentUser.code_postal = formData.code_postal;
            currentUser.ville = formData.ville;
            
            if (result.email_change_pending) {
                // L'email n'a PAS été changé, un code a été envoyé à l'ancienne adresse
                localStorage.setItem('user', JSON.stringify(currentUser));
                displayUserInfo(currentUser);
                cancelProfileEdit();
                showEmailVerificationModal();
                showSuccessMessage(result.message);
            } else {
                // Pas de changement d'email, tout est mis à jour
                currentUser.email = formData.email;
                localStorage.setItem('user', JSON.stringify(currentUser));
                displayUserInfo(currentUser);
                showSuccessMessage(result.message);
                cancelProfileEdit();
            }
        } else {
            showErrorMessage(result.message || 'Erreur lors de la mise à jour du profil');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

// Changement de mot de passe (envoie un email de reset)
async function requestPasswordChange() {
    const btn = document.getElementById('change-password-btn');
    const feedback = document.getElementById('password-change-feedback');
    
    // Désactiver le bouton pendant la requête
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Envoi en cours...';
    feedback.style.display = 'none';
    
    try {
        const response = await fetch(`${API_BASE_URL}/user/request-password-reset.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({ email: currentUser.email })
        });
        
        const result = await response.json();
        
        feedback.style.display = 'block';
        if (result.success) {
            feedback.className = 'mt-2 alert alert-success';
            feedback.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + result.message;
        } else {
            feedback.className = 'mt-2 alert alert-danger';
            feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> ' + (result.message || 'Erreur lors de l\'envoi');
        }
    } catch (error) {
        console.error('Erreur:', error);
        feedback.style.display = 'block';
        feedback.className = 'mt-2 alert alert-danger';
        feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Erreur de communication avec le serveur';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key me-1"></i> Changer mon mot de passe';
    }
}

// Vérification de changement d'email
function showEmailVerificationModal() {
    const modal = new bootstrap.Modal(document.getElementById('emailVerificationModal'));
    document.getElementById('email-verification-code').value = '';
    document.getElementById('email-verification-feedback').style.display = 'none';
    document.getElementById('confirm-email-change-btn').disabled = false;
    modal.show();
}

async function confirmEmailChange() {
    const code = document.getElementById('email-verification-code').value.trim();
    const feedback = document.getElementById('email-verification-feedback');
    const btn = document.getElementById('confirm-email-change-btn');
    
    if (code.length !== 6) {
        feedback.style.display = 'block';
        feedback.className = 'alert alert-danger mt-3';
        feedback.textContent = 'Veuillez saisir un code à 6 chiffres.';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Vérification...';
    feedback.style.display = 'none';
    
    try {
        const response = await fetch(`${API_BASE_URL}/user/confirm-email-change.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({ user_id: currentUser.id, code: code })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mettre à jour l'email dans localStorage
            currentUser.email = result.new_email;
            localStorage.setItem('user', JSON.stringify(currentUser));
            displayUserInfo(currentUser);
            
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('emailVerificationModal'));
            if (modal) modal.hide();
            
            showSuccessMessage('Adresse email mise à jour avec succès !');
        } else {
            feedback.style.display = 'block';
            feedback.className = 'alert alert-danger mt-3';
            feedback.textContent = result.message || 'Code incorrect.';
        }
    } catch (error) {
        console.error('Erreur:', error);
        feedback.style.display = 'block';
        feedback.className = 'alert alert-danger mt-3';
        feedback.textContent = 'Erreur de communication avec le serveur.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirmer';
    }
}

// Suppression de compte - Étape 1 : ouvrir la modale SUPPRIMER
function showDeleteAccountModal() {
    document.getElementById('delete-confirmation').value = '';
    document.getElementById('confirm-delete-btn').disabled = true;
    document.getElementById('delete-request-feedback').style.display = 'none';
    const modal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));
    modal.show();
}

// Suppression de compte - Étape 1b : envoyer le code par email
async function requestDeleteAccount() {
    const btn = document.getElementById('confirm-delete-btn');
    const feedback = document.getElementById('delete-request-feedback');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Envoi en cours...';
    feedback.style.display = 'none';
    
    try {
        const response = await fetch(`${API_BASE_URL}/user/request-delete-account.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({ user_id: currentUser.id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Fermer la modale étape 1
            const modal1 = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
            if (modal1) modal1.hide();
            
            // Ouvrir la modale étape 2 (saisie du code)
            setTimeout(() => {
                document.getElementById('delete-verification-code').value = '';
                document.getElementById('delete-verification-feedback').style.display = 'none';
                document.getElementById('confirm-delete-code-btn').disabled = false;
                const modal2 = new bootstrap.Modal(document.getElementById('deleteVerificationModal'));
                modal2.show();
            }, 500);
        } else {
            feedback.style.display = 'block';
            feedback.className = 'mt-2 alert alert-danger';
            feedback.textContent = result.message || 'Erreur lors de l\'envoi du code.';
        }
    } catch (error) {
        console.error('Erreur:', error);
        feedback.style.display = 'block';
        feedback.className = 'mt-2 alert alert-danger';
        feedback.textContent = 'Erreur de communication avec le serveur.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-envelope me-1"></i> Envoyer le code de confirmation';
    }
}

// Suppression de compte - Étape 2 : valider le code et supprimer
async function confirmDeleteAccount() {
    const code = document.getElementById('delete-verification-code').value.trim();
    const feedback = document.getElementById('delete-verification-feedback');
    const btn = document.getElementById('confirm-delete-code-btn');
    
    if (code.length !== 6) {
        feedback.style.display = 'block';
        feedback.className = 'alert alert-danger mt-3';
        feedback.textContent = 'Veuillez saisir un code à 6 chiffres.';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Suppression...';
    feedback.style.display = 'none';
    
    try {
        const response = await fetch(`${API_BASE_URL}/user/confirm-delete-account.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({ user_id: currentUser.id, code: code })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteVerificationModal'));
            if (modal) modal.hide();
            
            showSuccessMessage('Compte supprimé avec succès. Redirection...');
            
            // Déconnexion et redirection
            setTimeout(() => {
                localStorage.clear();
                window.location.href = '/';
            }, 2000);
        } else {
            feedback.style.display = 'block';
            feedback.className = 'alert alert-danger mt-3';
            feedback.textContent = result.message || 'Code incorrect.';
        }
    } catch (error) {
        console.error('Erreur:', error);
        feedback.style.display = 'block';
        feedback.className = 'alert alert-danger mt-3';
        feedback.textContent = 'Erreur de communication avec le serveur.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash me-1"></i> Supprimer définitivement';
    }
}

// Gestion des commandes
function showOrderDetailsModal(orderId) {
    const command = loadedCommands.find(c => c.id === orderId);
    
    if (!command) {
        showErrorMessage('Commande non trouvée');
        return;
    }
    
    // Remplir la modale
    document.getElementById('modal-order-id').textContent = command.id;
    
    const dateCommande = new Date(command.date_commande).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
    const datePrestation = command.date_prestation ? new Date(command.date_prestation + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' }) : '-';
    
    const modalContent = document.getElementById('order-details-content');
    modalContent.innerHTML = `
        <div class="order-details">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary"><i class="bi bi-receipt me-1"></i>Commande</h6>
                    <p><strong>N° :</strong> #${command.id}</p>
                    <p><strong>Date :</strong> ${dateCommande}</p>
                    <p><strong>Statut :</strong> <span class="badge ${getStatusClass(command.statut)}">${getStatusText(command.statut)}</span></p>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary"><i class="bi bi-egg-fried me-1"></i>Menu</h6>
                    <p><strong>Menu :</strong> ${command.menu_nom || 'N/A'}</p>
                    <p><strong>Personnes :</strong> ${command.nombre_personnes}</p>
                    <p><strong>Prix unitaire :</strong> ${command.prix_unitaire ? command.prix_unitaire.toFixed(2) + ' €/pers.' : '-'}</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary"><i class="bi bi-geo-alt me-1"></i>Livraison</h6>
                    <p><strong>Adresse :</strong> ${command.adresse_livraison || '-'}</p>
                    <p><strong>Ville :</strong> ${command.code_postal_livraison || ''} ${command.ville_livraison || '-'}</p>
                    <p><strong>Date :</strong> ${datePrestation}</p>
                    <p><strong>Heure :</strong> ${command.heure_prestation || '-'}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-primary"><i class="bi bi-calculator me-1"></i>Tarification</h6>
                    <p><strong>Sous-total :</strong> ${command.sous_total ? command.sous_total.toFixed(2) + ' €' : '-'}</p>
                    ${command.reduction_pourcent > 0 ? `<p class="text-success"><strong>Réduction (${command.reduction_pourcent}%) :</strong> -${command.reduction_montant.toFixed(2)} €</p>` : ''}
                    <p><strong>Frais livraison :</strong> ${command.frais_livraison ? command.frais_livraison.toFixed(2) + ' €' : '-'}</p>
                    <p class="fs-5 fw-bold"><strong>Total :</strong> ${command.total ? command.total.toFixed(2) + ' €' : 'N/A'}</p>
                </div>
            </div>
            ${command.notes ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="fw-bold text-primary"><i class="bi bi-chat-left-text me-1"></i>Notes</h6>
                        <p class="text-muted">${command.notes}</p>
                    </div>
                </div>
            ` : ''}
            ${command.statut !== 'en_attente' && command.statut !== 'annulee' ? `
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="fw-bold text-primary"><i class="bi bi-truck me-1"></i>Suivi</h6>
                        <div class="order-tracking">
                            ${generateOrderTracking(command.statut)}
                        </div>
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    // Configurer les actions
    const modalFooter = document.getElementById('modal-footer');
    let actionsHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>';
    
    if (canModifyOrder(command.statut)) {
        actionsHTML += `
            <button type="button" class="btn btn-danger" onclick="cancelOrder(${command.id})">
                <i class="bi bi-x-circle me-1"></i>
                Annuler
            </button>
        `;
    }
    
    if (command.statut === 'terminee') {
        actionsHTML += `
            <button type="button" class="btn btn-warning" onclick="openReviewModal(${command.id})">
                <i class="bi bi-star me-1"></i>
                Laisser un avis
            </button>
            <button type="button" class="btn btn-success" onclick="reorderCommand(${command.id})">
                <i class="bi bi-arrow-repeat me-1"></i>
                Commander à nouveau
            </button>
        `;
    }
    
    modalFooter.innerHTML = actionsHTML;
    
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    modal.show();
}

function editOrder(orderId) {
    currentEditingOrder = orderId;
    
    // Fermer la modale de détails si ouverte
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
    if (detailsModal) {
        detailsModal.hide();
    }
    
    // Trouver la commande dans les données locales
    const command = loadedCommands.find(c => c.id == orderId);
    if (!command) {
        showErrorMessage('Commande introuvable');
        return;
    }
    
    // Pré-remplir la modale
    document.getElementById('edit-order-id').textContent = orderId;
    document.getElementById('edit-menu-nom').textContent = command.menu_nom || 'Menu inconnu';
    document.getElementById('edit-nb-personnes').value = command.nombre_personnes || 1;
    document.getElementById('edit-nb-personnes').min = command.nombre_personnes_min || 1;
    document.getElementById('edit-personnes-info').textContent = `Minimum ${command.nombre_personnes_min || 1} personnes`;
    document.getElementById('edit-adresse').value = command.adresse_livraison || '';
    document.getElementById('edit-code-postal').value = command.code_postal_livraison || '';
    document.getElementById('edit-ville').value = command.ville_livraison || '';
    document.getElementById('edit-date').value = command.date_prestation || '';
    document.getElementById('edit-heure').value = command.heure_prestation || '';
    document.getElementById('edit-notes').value = command.notes || '';
    
    // Mettre à jour le récap
    updateEditRecap(command);
    
    // Initialiser la distance depuis la commande originale
    editDistanceLivraison = parseFloat(command.distance_km) || 0;
    
    // Écouter les changements pour recalculer le récap
    const nbInput = document.getElementById('edit-nb-personnes');
    nbInput.onchange = () => updateEditRecap(command);
    nbInput.oninput = () => updateEditRecap(command);
    
    // Écouter les changements d'adresse pour recalculer les frais de livraison
    const editAdresse = document.getElementById('edit-adresse');
    const editCp = document.getElementById('edit-code-postal');
    const editVille = document.getElementById('edit-ville');
    if (editAdresse) editAdresse.oninput = () => debouncedEditDeliveryFees(command);
    if (editCp) editCp.oninput = () => debouncedEditDeliveryFees(command);
    if (editVille) editVille.oninput = () => debouncedEditDeliveryFees(command);
    
    // Date minimum (demain)
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('edit-date').min = tomorrow.toISOString().split('T')[0];
    
    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

function updateEditRecap(command) {
    const nbPersonnes = parseInt(document.getElementById('edit-nb-personnes').value) || 1;
    const prixUnitaire = parseFloat(command.prix_unitaire) || 0;
    const nbMin = parseInt(command.nombre_personnes_min) || 1;
    const sousTotal = prixUnitaire * nbPersonnes;
    let reductionPourcent = 0;
    let reductionMontant = 0;
    if (nbPersonnes >= nbMin + 5) {
        reductionPourcent = 10;
        reductionMontant = sousTotal * 0.1;
    }
    
    const fraisLivraison = getEditDeliveryFees();
    const total = sousTotal - reductionMontant + fraisLivraison;
    
    const fmt = (v) => v.toFixed(2).replace('.', ',') + ' €';
    
    document.getElementById('edit-recap-sous-total').textContent = fmt(sousTotal);
    if (editDistanceLivraison > 0) {
        const majorationKm = Math.round(editDistanceLivraison * FRAIS_KM * 100) / 100;
        document.getElementById('edit-recap-frais').textContent = `${fmt(fraisLivraison)} (${fmt(FRAIS_LIVRAISON_BASE)} + ${editDistanceLivraison.toFixed(1)} km)`;
    } else {
        document.getElementById('edit-recap-frais').textContent = fmt(fraisLivraison);
    }
    document.getElementById('edit-recap-total').textContent = fmt(total);
    
    const reductionRow = document.getElementById('edit-recap-reduction-row');
    if (reductionPourcent > 0) {
        reductionRow.style.cssText = '';
        reductionRow.style.display = 'flex';
        document.getElementById('edit-recap-reduction').textContent = `- ${fmt(reductionMontant)} (${reductionPourcent}%)`;
    } else {
        reductionRow.style.cssText = 'display: none !important;';
    }
}

async function saveOrderEdit() {
    if (!currentEditingOrder) return;
    
    const command = loadedCommands.find(c => c.id == currentEditingOrder);
    if (!command) return;
    
    const nbPersonnes = parseInt(document.getElementById('edit-nb-personnes').value);
    const adresse = document.getElementById('edit-adresse').value.trim();
    const codePostal = document.getElementById('edit-code-postal').value.trim();
    const ville = document.getElementById('edit-ville').value.trim();
    const date = document.getElementById('edit-date').value;
    const heure = document.getElementById('edit-heure').value;
    const notes = document.getElementById('edit-notes').value.trim();
    
    if (!adresse || !codePostal || !ville || !date || !heure) {
        showErrorMessage('Veuillez remplir tous les champs obligatoires.');
        return;
    }
    
    // Forcer le recalcul des frais de livraison avant envoi
    if (editDeliveryFeeTimeout) clearTimeout(editDeliveryFeeTimeout);
    await calculateEditDeliveryFees();
    
    const prixUnitaire = parseFloat(command.prix_unitaire) || 0;
    const sousTotal = prixUnitaire * nbPersonnes;
    let reductionPourcent = 0;
    let reductionMontant = 0;
    if (nbPersonnes >= command.nombre_personnes_min + 5) {
        reductionPourcent = 10;
        reductionMontant = sousTotal * 0.1;
    }
    const fraisLivraison = getEditDeliveryFees();
    const total = sousTotal - reductionMontant + fraisLivraison;
    
    const payload = {
        order_id: currentEditingOrder,
        user_id: currentUser.id,
        nombre_personnes: nbPersonnes,
        prix_unitaire: prixUnitaire,
        adresse_livraison: adresse,
        code_postal_livraison: codePostal,
        ville_livraison: ville,
        date_prestation: date,
        heure_prestation: heure,
        frais_livraison: fraisLivraison,
        distance_km: editDistanceLivraison > 0 ? editDistanceLivraison : null,
        sous_total: sousTotal,
        reduction_pourcent: reductionPourcent,
        reduction_montant: reductionMontant,
        total: total,
        notes: notes || null
    };
    console.log('[saveOrderEdit] Payload envoyé:', payload);
    
    try {
        const response = await fetch(`${API_BASE_URL}/commands/update.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        console.log('[saveOrderEdit] Réponse serveur:', response.status, result);
        
        if (result.success) {
            showSuccessMessage('Commande modifiée avec succès');
            const modal = bootstrap.Modal.getInstance(document.getElementById('editOrderModal'));
            if (modal) modal.hide();
            loadUserCommands(currentUser.id);
        } else {
            showErrorMessage(result.message || 'Erreur lors de la modification de la commande');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

async function cancelOrder(orderId) {
    const confirmed = await confirmAction({
        title: 'Annuler la commande',
        message: 'Êtes-vous sûr de vouloir annuler cette commande ?',
        btnText: 'Annuler la commande',
        btnClass: 'btn-danger'
    });
    if (!confirmed) return;
    
    try {
        const response = await fetch(`${API_BASE_URL}/commands/cancel.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({ order_id: orderId, user_id: currentUser.id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccessMessage('Commande annulée avec succès');
            
            // Fermer la modale si ouverte
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
            if (detailsModal) {
                detailsModal.hide();
            }
            
            // Recharger les commandes
            loadUserCommands(currentUser.id);
        } else {
            showErrorMessage(result.message || 'Erreur lors de l\'annulation de la commande');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

function reorderCommand(orderId) {
    // Fermer la modale de détails
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
    if (detailsModal) {
        detailsModal.hide();
    }
    
    // Rediriger vers la page de commande avec l'ID de la commande à reproduire
    window.location.href = `/Carte?reorder=${orderId}`;
}

// Fonctions utilitaires
function loadAllCommands() {
    if (currentUser) {
        loadUserCommands(currentUser.id);
    }
}

// Messages
function showSuccessMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

function showErrorMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        <i class="bi bi-exclamation-triangle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Configuration des boutons de déconnexion
function setupLogoutButton() {
    const logoutBtns = document.querySelectorAll('#logout-btn, #header-logout-btn, #mobile-logout-btn');
    
    const logoutHandler = () => {
        const auth = new AuthValidator();
        auth.logout();
    };
    
    logoutBtns.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', logoutHandler);
        }
    });
}

// ============================================
// GESTION DES AVIS
// ============================================
var currentReviewNote = 0;

function openReviewModal(commandeId) {
    const command = loadedCommands.find(c => c.id === commandeId);
    if (!command) {
        showErrorMessage('Commande non trouvée');
        return;
    }

    // Fermer le modal de détails si ouvert
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
    if (detailsModal) detailsModal.hide();

    // Si un avis existe déjà, afficher le modal "avis existant"
    if (command.avis) {
        showExistingReviewModal(command);
        return;
    }

    // Remplir le modal
    document.getElementById('review-commande-id').value = commandeId;
    document.getElementById('review-commande-ref').textContent = '#' + commandeId;
    document.getElementById('review-commande-menu').textContent = command.menu_nom || '';

    // Réinitialiser
    currentReviewNote = 0;
    document.getElementById('review-note').value = 0;
    document.getElementById('review-commentaire').value = '';
    document.getElementById('review-char-count').textContent = '0';
    updateReviewStars(0);

    // Compteur de caractères
    const textarea = document.getElementById('review-commentaire');
    textarea.oninput = function() {
        document.getElementById('review-char-count').textContent = this.value.length;
    };

    setTimeout(() => {
        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        modal.show();
    }, 300);
}

function showExistingReviewModal(command) {
    const avis = command.avis;

    document.getElementById('review-exists-ref').textContent = '#' + command.id;
    document.getElementById('review-exists-menu').textContent = command.menu_nom || '';

    // Étoiles
    let starsHTML = '';
    for (let i = 1; i <= 5; i++) {
        starsHTML += i <= avis.note
            ? '<i class="bi bi-star-fill fs-4 text-warning"></i> '
            : '<i class="bi bi-star fs-4 text-muted"></i> ';
    }
    document.getElementById('review-exists-stars').innerHTML = starsHTML;

    // Commentaire
    document.getElementById('review-exists-commentaire').textContent = '"' + avis.commentaire + '"';

    // Date
    const dateAvis = new Date(avis.date);
    document.getElementById('review-exists-date').textContent = 'Publié le ' + dateAvis.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

    // Statut de validation
    const statusEl = document.getElementById('review-exists-status');
    if (avis.valide === 1) {
        statusEl.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validé et visible sur le site</span>';
    } else if (avis.valide === 2) {
        statusEl.innerHTML = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Refusé</span>';
    } else {
        statusEl.innerHTML = '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>En attente de validation</span>';
    }

    setTimeout(() => {
        const modal = new bootstrap.Modal(document.getElementById('reviewExistsModal'));
        modal.show();
    }, 300);
}

function setReviewNote(note) {
    currentReviewNote = note;
    document.getElementById('review-note').value = note;
    updateReviewStars(note);
}

function updateReviewStars(note) {
    const stars = document.querySelectorAll('#review-stars i');
    stars.forEach((star, index) => {
        if (index < note) {
            star.className = 'bi bi-star-fill fs-2 text-warning';
        } else {
            star.className = 'bi bi-star fs-2 text-muted';
        }
    });
}

async function submitReview() {
    const commandeId = parseInt(document.getElementById('review-commande-id').value);
    const note = currentReviewNote;
    const commentaire = document.getElementById('review-commentaire').value.trim();

    if (note < 1 || note > 5) {
        showErrorMessage('Veuillez sélectionner une note entre 1 et 5 étoiles');
        return;
    }

    if (commentaire.length < 10) {
        showErrorMessage('Le commentaire doit contenir au moins 10 caractères');
        return;
    }

    const submitBtn = document.getElementById('review-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi...';

    try {
        const response = await fetch(`${API_BASE_URL}/avis/create.php`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                commande_id: commandeId,
                note: note,
                commentaire: commentaire
            })
        });

        const result = await response.json();

        if (result.success) {
            showSuccessMessage(result.message || 'Merci pour votre avis !');
            const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
            if (modal) modal.hide();
        } else {
            showErrorMessage(result.message || 'Erreur lors de l\'envoi de l\'avis');
        }
    } catch (error) {
        console.error('Erreur soumission avis:', error);
        showErrorMessage('Erreur de connexion au serveur');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-send me-1"></i>Envoyer mon avis';
    }
}

// Initialiser les boutons de déconnexion
document.addEventListener('DOMContentLoaded', () => {
    setupLogoutButton();
});