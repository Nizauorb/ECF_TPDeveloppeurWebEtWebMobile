// frontend/js/employe-dashboard.js

// Prise de contrôle immédiate du header et du footer (layout plein écran)
(function() {
    const h = document.querySelector('.site-header');
    if (h) h.innerHTML = '';
    const f = document.querySelector('footer');
    if (f) f.style.display = 'none';
})();

// ============================================
// Authentification & variables globales
// ============================================
function requireEmployeAuth() {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
        window.location.href = '/Login';
        return false;
    }
    
    try {
        const user = JSON.parse(userStr);
        if (user.role !== 'employe' && user.role !== 'administrateur') {
            window.location.href = '/';
            return false;
        }
        return user;
    } catch (e) {
        console.error('Erreur parsing user:', e);
        window.location.href = '/Login';
        return false;
    }
}

var currentEmploye = null;
var csrfToken = null;
var allCommands = [];
var currentSection = 'commandes';
var currentStatusChangeOrderId = null;
var currentCancelOrderId = null;

// HTML de la barre de filtres injecté dynamiquement dans .site-header
var employeFiltersHeaderHTML = `
<div class="employe-header-bar d-block d-xl-none" style="background-color: #627D4A; padding: 0.75rem 1rem;">
    <div class="d-flex justify-content-between align-items-center">
        <span class="text-white fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Commandes</span>
        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#employeMobileFilters" aria-controls="employeMobileFilters">
            <i class="bi bi-funnel me-1"></i> Filtres
        </button>
    </div>
</div>
<div class="employe-header-bar d-none d-xl-block" style="background-color: #627D4A; padding: 0.85rem 0;">
    <div class="d-flex align-items-center gap-3 px-4">
        <select class="form-select form-select-sm" id="filterStatut" style="width: auto; min-width: 160px;">
            <option value="">Tous les statuts</option>
            <option value="en_attente">En attente</option>
            <option value="acceptee">Acceptée</option>
            <option value="en_preparation">En préparation</option>
            <option value="en_livraison">En livraison</option>
            <option value="livree">Livrée</option>
            <option value="attente_retour_materiel">Retour matériel</option>
            <option value="terminee">Terminée</option>
            <option value="annulee">Annulée</option>
        </select>
        <input type="text" class="form-control form-control-sm" id="filterClient" placeholder="Rechercher un client..." style="width: 200px;">
        <div class="d-flex align-items-center gap-2">
            <span class="text-white fw-medium small">Prestation du</span>
            <input type="date" class="form-control form-control-sm" id="filterDateFrom" style="width: auto;">
            <span class="text-white fw-medium small">au</span>
            <input type="date" class="form-control form-control-sm" id="filterDateTo" style="width: auto;">
        </div>
        <button class="btn btn-light btn-sm" onclick="applyEmployeFilters()">
            <i class="bi bi-funnel me-1"></i> Filtrer
        </button>
        <button class="btn btn-outline-light btn-sm" onclick="resetEmployeFilters()">
            <i class="bi bi-arrow-clockwise me-1"></i> Réinitialiser
        </button>
        <span class="text-white-50 small" id="employeFilterResults"></span>
    </div>
</div>
<div class="offcanvas offcanvas-end" tabindex="-1" id="employeMobileFilters" aria-labelledby="employeMobileFiltersLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold" id="employeMobileFiltersLabel">
            <i class="bi bi-funnel me-2"></i>Filtres
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-3">
            <label class="form-label fw-medium">Statut</label>
            <select class="form-select" id="filterStatutMobile">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="acceptee">Acceptée</option>
                <option value="en_preparation">En préparation</option>
                <option value="en_livraison">En livraison</option>
                <option value="livree">Livrée</option>
                <option value="attente_retour_materiel">Retour matériel</option>
                <option value="terminee">Terminée</option>
                <option value="annulee">Annulée</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">Rechercher un client</label>
            <input type="text" class="form-control" id="filterClientMobile" placeholder="Nom, prénom ou email...">
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium">Période de prestation</label>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted">Du</span>
                <input type="date" class="form-control" id="filterDateFromMobile">
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="text-muted">au</span>
                <input type="date" class="form-control" id="filterDateToMobile">
            </div>
        </div>
        <button class="btn btn-primary w-100 mb-2" onclick="applyEmployeFiltersMobile()">
            <i class="bi bi-funnel me-2"></i>Appliquer les filtres
        </button>
        <button class="btn btn-outline-secondary w-100" onclick="resetEmployeFiltersMobile()">
            <i class="bi bi-arrow-clockwise me-2"></i>Réinitialiser
        </button>
        <div class="mt-2 text-muted small" id="employeFilterResultsMobile"></div>
    </div>
</div>
`;

// ============================================
// CSRF & Headers
// ============================================
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

function getAuthHeaders() {
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

// ============================================
// Initialisation
// ============================================
(function() {
    currentEmploye = requireEmployeAuth();
    if (currentEmploye) {
        loadCSRFToken();
        displayEmployeInfo(currentEmploye);
        loadAllCommands();
        
        // Injecter la barre de filtres par défaut (section commandes)
        const siteHeader = document.querySelector('.site-header');
        if (siteHeader) siteHeader.innerHTML = employeFiltersHeaderHTML;
        
        // Lire le paramètre ?section= de l'URL pour afficher la bonne section
        const urlParams = new URLSearchParams(window.location.search);
        const sectionParam = urlParams.get('section');
        if (sectionParam) {
            const sectionMap = { 'orders': 'commandes', 'menus': 'menus', 'horaires': 'horaires', 'avis': 'avis', 'profile': 'profil' };
            const targetSection = sectionMap[sectionParam] || sectionParam;
            showSection(targetSection);
        }
    }
})();

function displayEmployeInfo(user) {
    const fullName = `${user.firstName || ''} ${user.lastName || ''}`.trim();
    
    const sidebarName = document.getElementById('sidebar-user-name');
    if (sidebarName) sidebarName.textContent = fullName;
    
    const sidebarNameMobile = document.getElementById('sidebar-user-name-mobile');
    if (sidebarNameMobile) sidebarNameMobile.textContent = fullName;
    
    // Profil
    const profilNom = document.getElementById('employe-profile-nom');
    if (profilNom) profilNom.value = user.lastName || '';
    const profilPrenom = document.getElementById('employe-profile-prenom');
    if (profilPrenom) profilPrenom.value = user.firstName || '';
    const profilEmail = document.getElementById('employe-profile-email');
    if (profilEmail) profilEmail.value = user.email || '';
    const profilTel = document.getElementById('employe-profile-telephone');
    if (profilTel) profilTel.value = user.phone || '';
}

// ============================================
// Navigation entre sections
// ============================================
function showSection(section) {
    // Sections disponibles
    const sections = ['commandes', 'menus', 'horaires', 'avis', 'profil'];
    
    // Sections désactivées (placeholder)
    const disabledSections = ['menus', 'horaires', 'avis'];
    if (disabledSections.includes(section)) {
        // Quand même afficher le placeholder
    }
    
    // Masquer toutes les sections
    sections.forEach(s => {
        const el = document.getElementById(`section-${s}`);
        if (el) el.style.display = 'none';
    });
    
    // Afficher la section demandée
    const target = document.getElementById(`section-${section}`);
    if (target) target.style.display = 'block';
    
    // Mettre à jour le titre
    const titles = {
        'commandes': 'Les Commandes',
        'menus': 'Les Menus',
        'horaires': 'Les Horaires',
        'avis': 'Les Avis',
        'profil': 'Mon Profil'
    };
    const titleEl = document.getElementById('employe-page-title');
    if (titleEl) titleEl.textContent = titles[section] || '';
    
    // Mettre à jour la sidebar active
    document.querySelectorAll('.dashboard-sidebar-nav .nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.section === section) {
            item.classList.add('active');
        }
    });
    
    // Injecter la barre de filtres dans le header ou vider le header
    const siteHeader = document.querySelector('.site-header');
    if (siteHeader) {
        if (section === 'commandes') {
            siteHeader.innerHTML = employeFiltersHeaderHTML;
        } else {
            siteHeader.innerHTML = '';
        }
    }
    
    currentSection = section;
}

function closeEmployeMenu() {
    const offcanvasEl = document.getElementById('employeDashboardMobileMenu');
    if (offcanvasEl) {
        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (offcanvas) offcanvas.hide();
    }
}

function employeLogout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/Login';
}

// ============================================
// Chargement des commandes
// ============================================
async function loadAllCommands(filters = {}) {
    try {
        document.getElementById('employe-loading-state').style.display = 'block';
        document.getElementById('employe-empty-state').style.display = 'none';
        document.getElementById('employe-orders-list').style.display = 'none';
        
        // Construire l'URL avec les filtres
        const params = new URLSearchParams();
        if (filters.statut) params.append('statut', filters.statut);
        if (filters.search) params.append('search', filters.search);
        if (filters.date_from) params.append('date_from', filters.date_from);
        if (filters.date_to) params.append('date_to', filters.date_to);
        
        const queryString = params.toString();
        const url = `${API_BASE_URL}/commands/all-commands.php${queryString ? '?' + queryString : ''}`;
        
        const token = localStorage.getItem('token');
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const result = await response.json();
        
        document.getElementById('employe-loading-state').style.display = 'none';
        
        if (result.success && result.data) {
            allCommands = result.data;
            updateStats(allCommands);
            displayEmployeOrders(allCommands);
            updateFilterResultsCount(result.count);
        } else {
            allCommands = [];
            updateStats([]);
            showEmployeEmptyState();
        }
    } catch (error) {
        console.error('Erreur chargement commandes:', error);
        document.getElementById('employe-loading-state').style.display = 'none';
        showEmployeEmptyState();
    }
}

function refreshCommands() {
    const filters = getCurrentFilters();
    loadAllCommands(filters);
}

// ============================================
// Statistiques rapides
// ============================================
function updateStats(commands) {
    const enAttente = commands.filter(c => c.statut === 'en_attente').length;
    const enCours = commands.filter(c => ['acceptee', 'en_preparation', 'en_livraison', 'livree'].includes(c.statut)).length;
    const retourMateriel = commands.filter(c => c.statut === 'attente_retour_materiel').length;
    const terminees = commands.filter(c => c.statut === 'terminee').length;
    
    document.getElementById('stat-en-attente').textContent = enAttente;
    document.getElementById('stat-en-cours').textContent = enCours;
    document.getElementById('stat-retour-materiel').textContent = retourMateriel;
    document.getElementById('stat-terminees').textContent = terminees;
}

// ============================================
// Affichage des commandes
// ============================================
function displayEmployeOrders(commands) {
    const list = document.getElementById('employe-orders-list');
    
    if (!commands || commands.length === 0) {
        showEmployeEmptyState();
        return;
    }
    
    list.innerHTML = '';
    
    commands.forEach(command => {
        const el = createEmployeOrderElement(command);
        list.appendChild(el);
    });
    
    list.style.display = 'block';
}

function showEmployeEmptyState() {
    document.getElementById('employe-empty-state').style.display = 'block';
    document.getElementById('employe-orders-list').style.display = 'none';
}

function createEmployeOrderElement(command) {
    const div = document.createElement('div');
    div.className = 'card border-0 shadow-sm mb-3 employe-order-card';
    div.id = `order-card-${command.id}`;
    
    const statusClass = getStatusBadgeClass(command.statut);
    const statusText = getStatusText(command.statut);
    
    const dateCommande = new Date(command.date_commande).toLocaleDateString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    });
    
    const datePrestation = command.date_prestation
        ? new Date(command.date_prestation + 'T00:00:00').toLocaleDateString('fr-FR', {
            day: '2-digit', month: 'long', year: 'numeric'
        })
        : '';
    
    const fmt = (v) => v ? v.toFixed(2).replace('.', ',') + ' €' : '-';
    
    div.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-receipt me-1"></i>Commande #${command.id}
                        <span class="badge ${statusClass} ms-2">${statusText}</span>
                    </h6>
                    <small class="text-muted">
                        <i class="bi bi-calendar me-1"></i>${dateCommande}
                        ${datePrestation ? ` — Prestation : ${datePrestation}` : ''}
                    </small>
                </div>
                <div class="fw-bold text-primary fs-5">${fmt(command.total)}</div>
            </div>
            
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <small class="text-muted d-block">Client</small>
                    <span class="fw-medium">${command.client_prenom || ''} ${command.client_nom || ''}</span>
                    <br><small class="text-muted">${command.client_email || ''}</small>
                    ${command.client_telephone ? `<br><small class="text-muted"><i class="bi bi-telephone me-1"></i>${command.client_telephone}</small>` : ''}
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Menu</small>
                    <span class="fw-medium">${command.menu_nom || 'Inconnu'}</span>
                    <br><small class="text-muted">${command.nombre_personnes || '-'} pers. × ${fmt(command.prix_unitaire)}</small>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Livraison</small>
                    <span class="fw-medium">${command.adresse_livraison || '-'}</span>
                    <br><small class="text-muted">${command.code_postal_livraison || ''} ${command.ville_livraison || ''}</small>
                </div>
            </div>
            
            ${command.motif_annulation ? `
                <div class="alert alert-danger py-2 mb-3">
                    <small><strong>Motif d'annulation :</strong> ${command.motif_annulation}</small>
                    ${command.mode_contact_annulation ? `<br><small><strong>Contact :</strong> ${command.mode_contact_annulation}</small>` : ''}
                </div>
            ` : ''}
            
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="showCommandeDetail(${command.id})">
                    <i class="bi bi-eye me-1"></i>Détails
                </button>
                ${command.statut !== 'annulee' && command.statut !== 'terminee' ? `
                    <button class="btn btn-sm btn-primary" onclick="openChangeStatusModal(${command.id})">
                        <i class="bi bi-arrow-right-circle me-1"></i>Changer statut
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="openCancelOrderModal(${command.id})">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                ` : ''}
            </div>
        </div>
    `;
    
    return div;
}

// ============================================
// Utilitaires statut
// ============================================
function getStatusBadgeClass(status) {
    const map = {
        'en_attente': 'bg-warning text-dark',
        'acceptee': 'bg-info text-dark',
        'en_preparation': 'bg-info text-white',
        'en_livraison': 'bg-primary text-white',
        'livree': 'bg-success text-white',
        'attente_retour_materiel': 'bg-secondary text-white',
        'terminee': 'bg-success text-white',
        'annulee': 'bg-danger text-white'
    };
    return map[status] || 'bg-warning text-dark';
}

function getStatusText(status) {
    const map = {
        'en_attente': 'En attente',
        'acceptee': 'Acceptée',
        'en_preparation': 'En préparation',
        'en_livraison': 'En livraison',
        'livree': 'Livrée',
        'attente_retour_materiel': 'Retour matériel',
        'terminee': 'Terminée',
        'annulee': 'Annulée'
    };
    return map[status] || 'Inconnu';
}

// ============================================
// Détails commande (modale)
// ============================================
function showCommandeDetail(orderId) {
    const command = allCommands.find(c => c.id === orderId);
    if (!command) return;
    
    const fmt = (v) => v ? v.toFixed(2).replace('.', ',') + ' €' : '-';
    const statusText = getStatusText(command.statut);
    const statusClass = getStatusBadgeClass(command.statut);
    
    const dateCommande = new Date(command.date_commande).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    
    const datePrestation = command.date_prestation
        ? new Date(command.date_prestation + 'T00:00:00').toLocaleDateString('fr-FR', {
            day: '2-digit', month: 'long', year: 'numeric'
        })
        : '-';
    
    document.getElementById('commandeDetailModalLabel').innerHTML = `
        <i class="bi bi-receipt me-2"></i>Commande #${command.id}
    `;
    
    document.getElementById('commandeDetailBody').innerHTML = `
        <div class="mb-3 text-center">
            <span class="badge ${statusClass} fs-6">${statusText}</span>
        </div>
        
        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>Client</h6>
        <div class="row g-2 mb-4">
            <div class="col-6"><small class="text-muted">Nom</small><br><strong>${command.client_prenom || ''} ${command.client_nom || ''}</strong></div>
            <div class="col-6"><small class="text-muted">Email</small><br><strong>${command.client_email || '-'}</strong></div>
            <div class="col-6"><small class="text-muted">Téléphone</small><br><strong>${command.client_telephone || '-'}</strong></div>
            <div class="col-6"><small class="text-muted">Date commande</small><br><strong>${dateCommande}</strong></div>
        </div>
        
        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-book me-2"></i>Menu</h6>
        <div class="row g-2 mb-4">
            <div class="col-6"><small class="text-muted">Menu</small><br><strong>${command.menu_nom || '-'}</strong></div>
            <div class="col-6"><small class="text-muted">Personnes</small><br><strong>${command.nombre_personnes || '-'} (min: ${command.nombre_personnes_min || '-'})</strong></div>
            <div class="col-6"><small class="text-muted">Prix unitaire</small><br><strong>${fmt(command.prix_unitaire)}</strong></div>
            <div class="col-6"><small class="text-muted">Sous-total</small><br><strong>${fmt(command.sous_total)}</strong></div>
            ${command.reduction_pourcent > 0 ? `
                <div class="col-6"><small class="text-muted">Réduction</small><br><strong class="text-success">-${fmt(command.reduction_montant)} (${command.reduction_pourcent}%)</strong></div>
            ` : ''}
            <div class="col-6"><small class="text-muted">Frais livraison</small><br><strong>${fmt(command.frais_livraison)}</strong></div>
            <div class="col-12"><small class="text-muted">Total</small><br><strong class="fs-5 text-primary">${fmt(command.total)}</strong></div>
        </div>
        
        <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-geo-alt me-2"></i>Livraison</h6>
        <div class="row g-2 mb-4">
            <div class="col-12"><small class="text-muted">Adresse</small><br><strong>${command.adresse_livraison || '-'}, ${command.code_postal_livraison || ''} ${command.ville_livraison || ''}</strong></div>
            <div class="col-6"><small class="text-muted">Date prestation</small><br><strong>${datePrestation}</strong></div>
            <div class="col-6"><small class="text-muted">Heure</small><br><strong>${command.heure_prestation || '-'}</strong></div>
            ${command.distance_km ? `<div class="col-6"><small class="text-muted">Distance</small><br><strong>${command.distance_km.toFixed(1)} km</strong></div>` : ''}
        </div>
        
        ${command.notes ? `
            <h6 class="fw-bold border-bottom pb-2 mb-3"><i class="bi bi-chat-text me-2"></i>Notes</h6>
            <p class="mb-4">${command.notes}</p>
        ` : ''}
        
        ${command.motif_annulation ? `
            <div class="alert alert-danger">
                <h6 class="fw-bold"><i class="bi bi-x-circle me-2"></i>Annulation</h6>
                <p class="mb-1"><strong>Motif :</strong> ${command.motif_annulation}</p>
                ${command.mode_contact_annulation ? `<p class="mb-0"><strong>Contact :</strong> ${command.mode_contact_annulation}</p>` : ''}
            </div>
        ` : ''}
    `;
    
    // Footer avec actions
    let footerHTML = '';
    if (command.statut !== 'annulee' && command.statut !== 'terminee') {
        footerHTML = `
            <button class="btn btn-primary" onclick="openChangeStatusModal(${command.id})" data-bs-dismiss="modal">
                <i class="bi bi-arrow-right-circle me-1"></i>Changer statut
            </button>
            <button class="btn btn-outline-danger" onclick="openCancelOrderModal(${command.id})" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i>Annuler
            </button>
        `;
    }
    document.getElementById('commandeDetailFooter').innerHTML = footerHTML;
    
    const modal = new bootstrap.Modal(document.getElementById('commandeDetailModal'));
    modal.show();
}

// ============================================
// Changement de statut
// ============================================
function openChangeStatusModal(orderId) {
    currentStatusChangeOrderId = orderId;
    const command = allCommands.find(c => c.id === orderId);
    if (!command) return;
    
    document.getElementById('status-order-ref').textContent = `#${orderId}`;
    document.getElementById('new-status-select').value = '';
    
    // Filtrer les statuts disponibles selon le statut actuel (progression logique)
    const select = document.getElementById('new-status-select');
    const allOptions = select.querySelectorAll('option');
    const statusOrder = ['en_attente', 'acceptee', 'en_preparation', 'en_livraison', 'livree', 'attente_retour_materiel', 'terminee'];
    const currentIndex = statusOrder.indexOf(command.statut);
    
    allOptions.forEach(opt => {
        if (opt.value === '') {
            opt.style.display = '';
            return;
        }
        const optIndex = statusOrder.indexOf(opt.value);
        // Afficher uniquement les statuts suivants dans la progression
        opt.style.display = (optIndex > currentIndex) ? '' : 'none';
    });
    
    const modal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
    modal.show();
}

async function confirmStatusChange() {
    const newStatus = document.getElementById('new-status-select').value;
    if (!newStatus || !currentStatusChangeOrderId) return;
    
    const btn = document.getElementById('confirm-status-change-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';
    
    try {
        const response = await fetch(`${API_BASE_URL}/commands/update-status.php`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                order_id: currentStatusChangeOrderId,
                statut: newStatus
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Fermer la modale
            const modal = bootstrap.Modal.getInstance(document.getElementById('changeStatusModal'));
            if (modal) modal.hide();
            
            showEmployeSuccess(`Statut mis à jour : ${getStatusText(newStatus)}`);
            refreshCommands();
        } else {
            showEmployeError(result.message || 'Erreur lors de la mise à jour du statut');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showEmployeError('Erreur de communication avec le serveur');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirmer';
        currentStatusChangeOrderId = null;
    }
}

// ============================================
// Annulation de commande
// ============================================
function openCancelOrderModal(orderId) {
    currentCancelOrderId = orderId;
    document.getElementById('cancel-order-ref').textContent = `#${orderId}`;
    document.getElementById('cancel-contact-mode').value = '';
    document.getElementById('cancel-motif').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    modal.show();
}

async function confirmCancelOrder() {
    const contactMode = document.getElementById('cancel-contact-mode').value;
    const motif = document.getElementById('cancel-motif').value.trim();
    
    if (!contactMode) {
        showEmployeError('Veuillez sélectionner le mode de contact utilisé');
        return;
    }
    if (!motif || motif.length < 10) {
        showEmployeError('Le motif doit contenir au moins 10 caractères');
        return;
    }
    if (!currentCancelOrderId) return;
    
    const btn = document.getElementById('confirm-cancel-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';
    
    const fullMotif = `[${contactMode === 'telephone' ? 'Appel téléphonique' : 'Email'}] ${motif}`;
    
    try {
        const response = await fetch(`${API_BASE_URL}/commands/update-status.php`, {
            method: 'POST',
            headers: getAuthHeaders(),
            credentials: 'include',
            body: JSON.stringify({
                order_id: currentCancelOrderId,
                statut: 'annulee',
                motif: fullMotif
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal'));
            if (modal) modal.hide();
            
            showEmployeSuccess('Commande annulée avec succès');
            refreshCommands();
        } else {
            showEmployeError(result.message || 'Erreur lors de l\'annulation');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showEmployeError('Erreur de communication avec le serveur');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Confirmer l\'annulation';
        currentCancelOrderId = null;
    }
}

// ============================================
// Filtres
// ============================================
function getCurrentFilters() {
    return {
        statut: document.getElementById('filterStatut')?.value || '',
        search: document.getElementById('filterClient')?.value || '',
        date_from: document.getElementById('filterDateFrom')?.value || '',
        date_to: document.getElementById('filterDateTo')?.value || ''
    };
}

function applyEmployeFilters() {
    const filters = getCurrentFilters();
    loadAllCommands(filters);
}

function resetEmployeFilters() {
    const ids = ['filterStatut', 'filterClient', 'filterDateFrom', 'filterDateTo'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    loadAllCommands();
}

// Filtres mobile — synchroniser avec les filtres desktop
function applyEmployeFiltersMobile() {
    // Copier les valeurs mobile vers desktop
    const mapping = {
        'filterStatutMobile': 'filterStatut',
        'filterClientMobile': 'filterClient',
        'filterDateFromMobile': 'filterDateFrom',
        'filterDateToMobile': 'filterDateTo'
    };
    Object.entries(mapping).forEach(([mobileId, desktopId]) => {
        const mobileEl = document.getElementById(mobileId);
        const desktopEl = document.getElementById(desktopId);
        if (mobileEl && desktopEl) desktopEl.value = mobileEl.value;
    });
    
    // Fermer l'offcanvas
    const offcanvasEl = document.getElementById('employeMobileFilters');
    if (offcanvasEl) {
        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (offcanvas) offcanvas.hide();
    }
    
    applyEmployeFilters();
}

function resetEmployeFiltersMobile() {
    const ids = ['filterStatutMobile', 'filterClientMobile', 'filterDateFromMobile', 'filterDateToMobile'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    resetEmployeFilters();
}

function updateFilterResultsCount(count) {
    const text = `${count} commande${count > 1 ? 's' : ''}`;
    const el = document.getElementById('employeFilterResults');
    if (el) el.textContent = text;
    const elMobile = document.getElementById('employeFilterResultsMobile');
    if (elMobile) elMobile.textContent = text;
}

// ============================================
// Messages toast
// ============================================
function showEmployeSuccess(message) {
    showEmployeToast(message, 'success');
}

function showEmployeError(message) {
    showEmployeToast(message, 'danger');
}

function showEmployeToast(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alert.innerHTML = `
        <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
    `;
    document.body.appendChild(alert);
    setTimeout(() => {
        if (alert.parentNode) alert.remove();
    }, 5000);
}
