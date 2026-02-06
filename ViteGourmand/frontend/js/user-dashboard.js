// frontend/js/user-dashboard.js
function requireAuth() {
    const auth = new AuthValidator();
    
    if (!auth.isLoggedIn()) {
        window.location.href = '/Login';
        return false;
    }
    
    return auth.getCurrentUser();
}

// Variables globales
let currentUser = null;
let currentEditingOrder = null;

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    currentUser = requireAuth();
    if (currentUser) {
        console.log('Utilisateur connecté:', currentUser);
        
        // Afficher les infos utilisateur dans la sidebar
        displayUserInfo(currentUser);
        
        // Charger les commandes par défaut
        loadUserCommands(currentUser.id);
        
        // Mettre en place les écouteurs d'événements
        setupEventListeners();
    }
});

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
    // Sidebar desktop
    const sidebarUserName = document.getElementById('sidebar-user-name');
    if (sidebarUserName) {
        sidebarUserName.textContent = `${user.prenom} ${user.nom}`;
    }
    
    // Mobile header
    const sidebarUserNameMobile = document.getElementById('sidebar-user-name-mobile');
    if (sidebarUserNameMobile) {
        sidebarUserNameMobile.textContent = `${user.prenom} ${user.nom}`;
    }
    
    // Header mobile (legacy)
    const headerUserName = document.getElementById('header-user-name');
    const mobileUserName = document.getElementById('mobile-user-name');
    const mobileUserEmail = document.getElementById('mobile-user-email');
    
    if (headerUserName) {
        headerUserName.textContent = `${user.prenom} ${user.nom}`;
    }
    if (mobileUserName) {
        mobileUserName.textContent = `${user.prenom} ${user.nom}`;
    }
    if (mobileUserEmail) {
        mobileUserEmail.textContent = user.email;
    }
    
    // Formulaire de profil
    document.getElementById('profile-nom').value = user.nom || '';
    document.getElementById('profile-prenom').value = user.prenom || '';
    document.getElementById('profile-email').value = user.email || '';
    document.getElementById('profile-telephone').value = user.telephone || '';
    document.getElementById('profile-adresse').value = user.adresse || '';
}

// Navigation
function showMyOrders() {
    // Masquer la section profil
    document.getElementById('profile-section').style.display = 'none';
    
    // Afficher la section commandes
    document.getElementById('orders-section').style.display = 'block';
    
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
        
        const response = await fetch(`/api/commands/user/${userId}`);
        const result = await response.json();
        
        // Masquer l'état de chargement
        document.getElementById('loading-state').style.display = 'none';
        
        if (result.success && result.data) {
            displayOrders(result.data);
        } else {
            showEmptyState();
        }
    } catch (error) {
        console.error('Erreur:', error);
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
                ${command.notes ? `
                    <div class="detail-row">
                        <span class="detail-label">Notes :</span>
                        <span class="detail-value">${command.notes}</span>
                    </div>
                ` : ''}
            </div>
            
            ${command.statut !== 'en_attente' ? `
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
                ${command.statut === 'livre' ? `
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
        { id: 'accepte', title: 'Commande acceptée', icon: 'bi-check-circle' },
        { id: 'en_preparation', title: 'En préparation', icon: 'bi-fire' },
        { id: 'pret', title: 'Prête', icon: 'bi-check2-square' },
        { id: 'livre', title: 'Livrée', icon: 'bi-truck' }
    ];
    
    let html = '';
    let foundCurrent = false;
    
    steps.forEach((step, index) => {
        let stepClass = 'pending';
        if (step.id === status) {
            stepClass = 'current';
            foundCurrent = true;
        } else if (foundCurrent || (status === 'livre' && index < 4)) {
            stepClass = 'completed';
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
        'en_attente': 'status-en_attente',
        'accepte': 'status-accepte',
        'en_preparation': 'status-en_preparation',
        'pret': 'status-pret',
        'livre': 'status-livre',
        'annule': 'status-annule'
    };
    return statusMap[status] || 'status-en_attente';
}

function getStatusText(status) {
    const statusMap = {
        'en_attente': 'En attente',
        'accepte': 'Acceptée',
        'en_preparation': 'En préparation',
        'pret': 'Prête',
        'livre': 'Livrée',
        'annule': 'Annulée'
    };
    return statusMap[status] || 'Inconnu';
}

function canModifyOrder(status) {
    return status === 'en_attente' || status === 'accepte';
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
        nom: document.getElementById('profile-nom').value,
        prenom: document.getElementById('profile-prenom').value,
        email: document.getElementById('profile-email').value,
        telephone: document.getElementById('profile-telephone').value,
        adresse: document.getElementById('profile-adresse').value
    };
    
    try {
        const response = await fetch('/api/user/profile', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Mettre à jour les infos utilisateur
            currentUser = { ...currentUser, ...formData };
            localStorage.setItem('user', JSON.stringify(currentUser));
            
            // Afficher un message de succès
            showSuccessMessage('Profil mis à jour avec succès');
            
            // Désactiver l'édition
            cancelProfileEdit();
        } else {
            showErrorMessage(result.message || 'Erreur lors de la mise à jour du profil');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

// Suppression de compte
function confirmDeleteAccount() {
    const modal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));
    modal.show();
}

async function deleteAccount() {
    try {
        const response = await fetch('/api/user/delete', {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccessMessage('Compte supprimé avec succès');
            
            // Déconnexion et redirection
            setTimeout(() => {
                localStorage.clear();
                window.location.href = '/';
            }, 2000);
        } else {
            showErrorMessage(result.message || 'Erreur lors de la suppression du compte');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

// Gestion des commandes
async function showOrderDetailsModal(orderId) {
    try {
        const response = await fetch(`/api/commands/${orderId}`);
        const result = await response.json();
        
        if (result.success) {
            const command = result.data;
            
            // Remplir la modale
            document.getElementById('modal-order-id').textContent = command.id;
            
            const modalContent = document.getElementById('order-details-content');
            modalContent.innerHTML = `
                <div class="order-details">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informations de la commande</h6>
                            <p><strong>N° Commande:</strong> #${command.id}</p>
                            <p><strong>Date:</strong> ${new Date(command.date_commande).toLocaleDateString('fr-FR')}</p>
                            <p><strong>Statut:</strong> ${getStatusText(command.statut)}</p>
                            <p><strong>Total:</strong> ${command.total ? command.total.toFixed(2) + ' €' : 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Menu commandé</h6>
                            <p><strong>Nom:</strong> ${command.menu_nom || 'N/A'}</p>
                            <p><strong>Description:</strong> ${command.menu_description || 'N/A'}</p>
                        </div>
                    </div>
                    ${command.notes ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Notes</h6>
                                <p class="text-muted">${command.notes}</p>
                            </div>
                        </div>
                    ` : ''}
                    ${command.statut !== 'en_attente' ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Suivi de la commande</h6>
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
                    <button type="button" class="btn btn-warning" onclick="editOrder(${command.id})">
                        <i class="bi bi-pencil me-1"></i>
                        Modifier
                    </button>
                    <button type="button" class="btn btn-danger" onclick="cancelOrder(${command.id})">
                        <i class="bi bi-x-circle me-1"></i>
                        Annuler
                    </button>
                `;
            }
            
            if (command.statut === 'livre') {
                actionsHTML += `
                    <button type="button" class="btn btn-success" onclick="reorderCommand(${command.id})">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Commander à nouveau
                    </button>
                `;
            }
            
            modalFooter.innerHTML = actionsHTML;
            
            // Afficher la modale
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            modal.show();
        } else {
            showErrorMessage(result.message || 'Erreur lors du chargement des détails');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showErrorMessage('Erreur de communication avec le serveur');
    }
}

function editOrder(orderId) {
    currentEditingOrder = orderId;
    
    // Fermer la modale de détails si ouverte
    const detailsModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
    if (detailsModal) {
        detailsModal.hide();
    }
    
    // Ouvrir la modale d'édition
    document.getElementById('edit-order-id').textContent = orderId;
    
    // Pré-remplir les notes existantes
    // TODO: Charger les notes existantes depuis l'API
    
    const modal = new bootstrap.Modal(document.getElementById('editOrderModal'));
    modal.show();
}

async function saveOrderEdit() {
    if (!currentEditingOrder) return;
    
    const notes = document.getElementById('edit-notes').value;
    
    try {
        const response = await fetch(`/api/commands/${currentEditingOrder}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify({ notes })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccessMessage('Commande modifiée avec succès');
            
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('editOrderModal'));
            if (modal) {
                modal.hide();
            }
            
            // Recharger les commandes
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
    if (!confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/commands/${orderId}/cancel`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
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

// Initialiser les boutons de déconnexion
document.addEventListener('DOMContentLoaded', () => {
    setupLogoutButton();
});