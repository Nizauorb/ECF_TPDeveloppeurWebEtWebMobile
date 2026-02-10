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
var orderSortDirection = 'desc';

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
        <button class="btn btn-outline-light btn-sm" onclick="toggleOrderSort()" id="btnSortOrders" title="Inverser le tri">
            <i class="bi bi-sort-down me-1"></i> Plus récentes
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
        <button class="btn btn-outline-primary w-100 mt-2" onclick="toggleOrderSort()" id="btnSortOrdersMobile">
            <i class="bi bi-sort-down me-1"></i> Plus récentes d'abord
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
    const disabledSections = ['horaires', 'avis'];
    if (disabledSections.includes(section)) {
        // Quand même afficher le placeholder
    }
    
    // Charger les menus si on navigue vers la section menus
    if (section === 'menus') {
        loadAllMenus();
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
    
    // Mettre à jour le bouton d'action du header
    const headerActions = document.getElementById('employe-header-actions');
    if (headerActions) {
        if (section === 'commandes') {
            headerActions.innerHTML = '<button class="btn btn-outline-primary btn-sm" onclick="refreshCommands()"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>';
        } else if (section === 'menus') {
            headerActions.innerHTML = '<button class="btn btn-outline-primary btn-sm" onclick="loadAllMenus()"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</button>';
        } else {
            headerActions.innerHTML = '';
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
    
    // Trier les commandes selon la direction choisie (par ID = ordre de création)
    const sorted = [...commands].sort((a, b) => {
        return orderSortDirection === 'desc' ? b.id - a.id : a.id - b.id;
    });
    
    list.innerHTML = '';
    
    sorted.forEach(command => {
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
// Tri des commandes
// ============================================
function toggleOrderSort() {
    orderSortDirection = orderSortDirection === 'desc' ? 'asc' : 'desc';
    
    // Mettre à jour le texte et l'icône des boutons (desktop + mobile)
    const isDesc = orderSortDirection === 'desc';
    const icon = isDesc ? 'bi-sort-down' : 'bi-sort-up';
    const label = isDesc ? 'Plus récentes' : 'Plus anciennes';
    const labelMobile = isDesc ? 'Plus récentes d\'abord' : 'Plus anciennes d\'abord';
    
    const btnDesktop = document.getElementById('btnSortOrders');
    if (btnDesktop) btnDesktop.innerHTML = `<i class="bi ${icon} me-1"></i> ${label}`;
    
    const btnMobile = document.getElementById('btnSortOrdersMobile');
    if (btnMobile) btnMobile.innerHTML = `<i class="bi ${icon} me-1"></i> ${labelMobile}`;
    
    // Ré-afficher les commandes avec le nouveau tri
    displayEmployeOrders(allCommands);
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

// ============================================
// GESTION DES MENUS — CRUD
// ============================================
var allMenusData = [];
var allPlatsData = [];
var menuToDeleteId = null;

async function loadAllMenus() {
    const loadingState = document.getElementById('menus-loading-state');
    const emptyState = document.getElementById('menus-empty-state');
    const menusList = document.getElementById('menus-list');

    if (loadingState) loadingState.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (menusList) menusList.style.display = 'none';

    try {
        const response = await fetch(`${API_BASE_URL}/menus/list.php?include_inactive=1`, {
            headers: getAuthHeaders()
        });
        const result = await response.json();

        if (loadingState) loadingState.style.display = 'none';

        if (!result.success) {
            showEmployeError('Erreur lors du chargement des menus');
            return;
        }

        allMenusData = result.data || [];

        if (allMenusData.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (menusList) menusList.style.display = 'flex';
        renderMenusList(allMenusData);
        updateMenusStats(allMenusData);

    } catch (error) {
        console.error('Erreur chargement menus:', error);
        if (loadingState) loadingState.style.display = 'none';
        showEmployeError('Erreur de connexion au serveur');
    }
}

function renderMenusList(menus) {
    const container = document.getElementById('menus-list');
    if (!container) return;

    container.innerHTML = menus.map(menu => {
        const stockClass = menu.stock_disponible >= 8 ? 'bg-success' : menu.stock_disponible >= 4 ? 'bg-warning' : 'bg-danger';
        const statusBadge = menu.actif
            ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Actif</span>'
            : '<span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i>Inactif</span>';

        const themeLabels = { 'Classique': 'Classique', 'Noel': 'Noël', 'Paques': 'Pâques', 'Event': 'Événement' };
        const themeLabel = themeLabels[menu.theme] || menu.theme;

        const platsCount = (menu.plats || []).length;
        const allergenes = (menu.allergenes || []).slice(0, 3).join(', ');
        const allergenesMore = (menu.allergenes || []).length > 3 ? ` +${menu.allergenes.length - 3}` : '';

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 ${!menu.actif ? 'opacity-50' : ''}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold mb-0">${escapeHtml(menu.titre)}</h5>
                            ${statusBadge}
                        </div>
                        <p class="text-muted small mb-2">${escapeHtml((menu.description || '').substring(0, 100))}${(menu.description || '').length > 100 ? '...' : ''}</p>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-primary">${themeLabel}</span>
                            <span class="badge bg-info">${escapeHtml(menu.regime)}</span>
                            <span class="badge ${stockClass}">${menu.stock_disponible} en stock</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-primary">${parseFloat(menu.prix_par_personne).toFixed(2)}€/pers.</span>
                            <span class="text-muted small">${menu.nombre_personnes_min}+ pers.</span>
                        </div>
                        <div class="text-muted small mb-3">
                            <i class="bi bi-egg-fried me-1"></i>${platsCount} plat(s)
                            ${allergenes ? ` · <i class="bi bi-exclamation-triangle me-1"></i>${escapeHtml(allergenes)}${allergenesMore}` : ''}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm flex-grow-1" onclick="openMenuEditModal(${menu.id})">
                                <i class="bi bi-pencil me-1"></i>Modifier
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="openMenuDeleteModal(${menu.id}, '${escapeHtml(menu.titre)}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function updateMenusStats(menus) {
    const actifs = menus.filter(m => m.actif).length;
    const inactifs = menus.filter(m => !m.actif).length;
    const stockBas = menus.filter(m => m.actif && m.stock_disponible < 4).length;

    // Compter les plats uniques
    const platIds = new Set();
    menus.forEach(m => (m.plats || []).forEach(p => platIds.add(p.id)));

    document.getElementById('stat-menus-total').textContent = actifs;
    document.getElementById('stat-plats-total').textContent = platIds.size;
    document.getElementById('stat-menus-stock-bas').textContent = stockBas;
    document.getElementById('stat-menus-inactifs').textContent = inactifs;
}

// --- Chargement des plats pour la modale ---
async function loadPlatsForForm(selectedPlatIds = []) {
    const container = document.getElementById('menu-form-plats-list');
    if (!container) return;

    try {
        const response = await fetch(`${API_BASE_URL}/menus/plats/list.php`, {
            headers: getAuthHeaders()
        });
        const result = await response.json();

        if (!result.success) {
            container.innerHTML = '<p class="text-danger small mb-0">Erreur de chargement des plats</p>';
            return;
        }

        allPlatsData = result.data || [];

        if (allPlatsData.length === 0) {
            container.innerHTML = '<p class="text-muted small mb-0">Aucun plat disponible</p>';
            return;
        }

        const typeLabels = { 'entree': 'Entrées', 'plat': 'Plats principaux', 'dessert': 'Desserts' };
        const grouped = { 'entree': [], 'plat': [], 'dessert': [] };
        allPlatsData.forEach(p => {
            if (grouped[p.type]) grouped[p.type].push(p);
        });

        let html = '';
        for (const [type, plats] of Object.entries(grouped)) {
            if (plats.length === 0) continue;
            html += `<div class="mb-2"><strong class="small text-uppercase text-muted">${typeLabels[type]}</strong></div>`;
            plats.forEach(p => {
                const checked = selectedPlatIds.includes(p.id) ? 'checked' : '';
                html += `
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" value="${p.id}" id="plat-check-${p.id}" ${checked}>
                        <label class="form-check-label small" for="plat-check-${p.id}">
                            ${escapeHtml(p.nom)}
                            ${(p.allergenes || []).length > 0 ? `<span class="text-warning ms-1"><i class="bi bi-exclamation-triangle-fill"></i></span>` : ''}
                        </label>
                    </div>
                `;
            });
        }

        container.innerHTML = html;

    } catch (error) {
        console.error('Erreur chargement plats:', error);
        container.innerHTML = '<p class="text-danger small mb-0">Erreur de connexion</p>';
    }
}

// --- Ouvrir la modale de création ---
function openMenuCreateModal() {
    document.getElementById('menu-form-id').value = '';
    document.getElementById('menu-form-titre').value = '';
    document.getElementById('menu-form-key').value = '';
    document.getElementById('menu-form-key').removeAttribute('readonly');
    document.getElementById('menu-form-description').value = '';
    document.getElementById('menu-form-theme').value = '';
    document.getElementById('menu-form-regime').value = 'Classique';
    document.getElementById('menu-form-image').value = '';
    document.getElementById('menu-form-prix').value = '';
    document.getElementById('menu-form-personnes-min').value = '2';
    document.getElementById('menu-form-stock').value = '10';
    document.getElementById('menu-form-conditions').value = '';
    document.getElementById('menu-form-actif').checked = true;

    document.getElementById('menuFormModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nouveau menu';
    document.getElementById('menu-form-submit-btn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Créer le menu';

    loadPlatsForForm([]);

    const modal = new bootstrap.Modal(document.getElementById('menuFormModal'));
    modal.show();
}

// --- Ouvrir la modale d'édition ---
function openMenuEditModal(menuId) {
    const menu = allMenusData.find(m => m.id === menuId);
    if (!menu) return;

    document.getElementById('menu-form-id').value = menu.id;
    document.getElementById('menu-form-titre').value = menu.titre || '';
    document.getElementById('menu-form-key').value = menu.menu_key || '';
    document.getElementById('menu-form-key').setAttribute('readonly', 'readonly');
    document.getElementById('menu-form-description').value = menu.description || '';
    document.getElementById('menu-form-theme').value = menu.theme || '';
    document.getElementById('menu-form-regime').value = menu.regime || 'Classique';
    document.getElementById('menu-form-image').value = menu.image || '';
    document.getElementById('menu-form-prix').value = menu.prix_par_personne || '';
    document.getElementById('menu-form-personnes-min').value = menu.nombre_personnes_min || 2;
    document.getElementById('menu-form-stock').value = menu.stock_disponible ?? 10;
    document.getElementById('menu-form-conditions').value = menu.conditions_commande || '';
    document.getElementById('menu-form-actif').checked = menu.actif;

    document.getElementById('menuFormModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier le menu';
    document.getElementById('menu-form-submit-btn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Enregistrer';

    const selectedPlatIds = (menu.plats || []).map(p => p.id);
    loadPlatsForForm(selectedPlatIds);

    const modal = new bootstrap.Modal(document.getElementById('menuFormModal'));
    modal.show();
}

// --- Soumettre le formulaire (création ou modification) ---
async function submitMenuForm() {
    const menuId = document.getElementById('menu-form-id').value;
    const isEdit = menuId !== '';

    const titre = document.getElementById('menu-form-titre').value.trim();
    const menuKey = document.getElementById('menu-form-key').value.trim();
    const theme = document.getElementById('menu-form-theme').value;
    const prix = document.getElementById('menu-form-prix').value;
    const nbMin = document.getElementById('menu-form-personnes-min').value;

    if (!titre || !menuKey || !theme || !prix || !nbMin) {
        showEmployeError('Veuillez remplir tous les champs obligatoires');
        return;
    }

    // Récupérer les plats cochés
    const platCheckboxes = document.querySelectorAll('#menu-form-plats-list input[type="checkbox"]:checked');
    const platIds = Array.from(platCheckboxes).map(cb => parseInt(cb.value));

    const data = {
        titre: titre,
        menu_key: menuKey,
        description: document.getElementById('menu-form-description').value.trim(),
        theme: theme,
        regime: document.getElementById('menu-form-regime').value,
        image: document.getElementById('menu-form-image').value.trim(),
        prix_par_personne: parseFloat(prix),
        nombre_personnes_min: parseInt(nbMin),
        stock_disponible: parseInt(document.getElementById('menu-form-stock').value) || 0,
        conditions_commande: document.getElementById('menu-form-conditions').value.trim(),
        actif: document.getElementById('menu-form-actif').checked,
        plat_ids: platIds
    };

    if (isEdit) {
        data.id = parseInt(menuId);
    }

    const submitBtn = document.getElementById('menu-form-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';

    try {
        const url = isEdit ? `${API_BASE_URL}/menus/update.php` : `${API_BASE_URL}/menus/create.php`;
        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: getAuthHeaders(),
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showEmployeSuccess(isEdit ? 'Menu modifié avec succès' : 'Menu créé avec succès');
            const modal = bootstrap.Modal.getInstance(document.getElementById('menuFormModal'));
            if (modal) modal.hide();
            loadAllMenus();
        } else {
            showEmployeError(result.message || 'Erreur lors de l\'opération');
        }

    } catch (error) {
        console.error('Erreur soumission menu:', error);
        showEmployeError('Erreur de connexion au serveur');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = isEdit
            ? '<i class="bi bi-check-circle me-1"></i>Enregistrer'
            : '<i class="bi bi-check-circle me-1"></i>Créer le menu';
    }
}

// --- Suppression (soft delete) ---
function openMenuDeleteModal(menuId, menuTitre) {
    menuToDeleteId = menuId;
    document.getElementById('menu-delete-name').textContent = menuTitre;
    const modal = new bootstrap.Modal(document.getElementById('menuDeleteModal'));
    modal.show();
}

async function confirmDeleteMenu() {
    if (!menuToDeleteId) return;

    const confirmBtn = document.getElementById('menu-delete-confirm-btn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';

    try {
        const response = await fetch(`${API_BASE_URL}/menus/delete.php?id=${menuToDeleteId}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        const result = await response.json();

        if (result.success) {
            showEmployeSuccess(result.message || 'Menu désactivé avec succès');
            const modal = bootstrap.Modal.getInstance(document.getElementById('menuDeleteModal'));
            if (modal) modal.hide();
            loadAllMenus();
        } else {
            showEmployeError(result.message || 'Erreur lors de la désactivation');
        }

    } catch (error) {
        console.error('Erreur suppression menu:', error);
        showEmployeError('Erreur de connexion au serveur');
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-trash me-1"></i>Désactiver';
        menuToDeleteId = null;
    }
}

// --- Utilitaire : échapper le HTML ---
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// GESTION DES PLATS — CRUD
// ============================================
var allPlatsDisplayData = [];
var platToDeleteId = null;

async function loadAllPlats() {
    const loadingState = document.getElementById('plats-loading-state');
    const emptyState = document.getElementById('plats-empty-state');
    const tableContainer = document.getElementById('plats-table-container');

    if (loadingState) loadingState.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (tableContainer) tableContainer.style.display = 'none';

    try {
        const response = await fetch(`${API_BASE_URL}/menus/plats/list.php`, {
            headers: getAuthHeaders()
        });
        const result = await response.json();

        if (loadingState) loadingState.style.display = 'none';

        if (!result.success) {
            showEmployeError('Erreur lors du chargement des plats');
            return;
        }

        allPlatsDisplayData = result.data || [];

        if (allPlatsDisplayData.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (tableContainer) tableContainer.style.display = 'block';
        renderPlatsTable(allPlatsDisplayData);

    } catch (error) {
        console.error('Erreur chargement plats:', error);
        if (loadingState) loadingState.style.display = 'none';
        showEmployeError('Erreur de connexion au serveur');
    }
}

function renderPlatsTable(plats) {
    const tbody = document.getElementById('plats-table-body');
    if (!tbody) return;

    const typeLabels = { 'entree': 'Entrée', 'plat': 'Plat principal', 'dessert': 'Dessert' };
    const typeBadgeClass = { 'entree': 'bg-info', 'plat': 'bg-primary', 'dessert': 'bg-success' };

    // Trouver les menus associés à chaque plat
    const platMenusMap = {};
    allMenusData.forEach(menu => {
        (menu.plats || []).forEach(p => {
            if (!platMenusMap[p.id]) platMenusMap[p.id] = [];
            platMenusMap[p.id].push(menu.titre);
        });
    });

    tbody.innerHTML = plats.map(plat => {
        const allergenes = (plat.allergenes || []);
        const allergenesHtml = allergenes.length > 0
            ? allergenes.map(a => `<span class="badge bg-warning text-dark me-1 mb-1">${escapeHtml(a)}</span>`).join('')
            : '<span class="text-muted small">Aucun</span>';

        const menusAssocies = platMenusMap[plat.id] || [];
        const menusHtml = menusAssocies.length > 0
            ? menusAssocies.map(m => `<span class="badge bg-light text-dark border me-1 mb-1">${escapeHtml(m)}</span>`).join('')
            : '<span class="text-muted small">Aucun menu</span>';

        return `
            <tr>
                <td class="fw-medium">${escapeHtml(plat.nom)}</td>
                <td><span class="badge ${typeBadgeClass[plat.type] || 'bg-secondary'}">${typeLabels[plat.type] || plat.type}</span></td>
                <td><div class="d-flex flex-wrap">${allergenesHtml}</div></td>
                <td><div class="d-flex flex-wrap">${menusHtml}</div></td>
                <td class="text-end">
                    <button class="btn btn-outline-primary btn-sm me-1" onclick="openPlatEditModal(${plat.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="openPlatDeleteModal(${plat.id}, '${escapeHtml(plat.nom).replace(/'/g, "\\'")}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filterPlatsDisplay() {
    const filterType = document.getElementById('plats-filter-type').value;
    if (!filterType) {
        renderPlatsTable(allPlatsDisplayData);
    } else {
        const filtered = allPlatsDisplayData.filter(p => p.type === filterType);
        renderPlatsTable(filtered);

        const emptyState = document.getElementById('plats-empty-state');
        const tableContainer = document.getElementById('plats-table-container');
        if (filtered.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            if (tableContainer) tableContainer.style.display = 'none';
        } else {
            if (emptyState) emptyState.style.display = 'none';
            if (tableContainer) tableContainer.style.display = 'block';
        }
    }
}

// --- Ouvrir la modale de création de plat ---
function openPlatCreateModal() {
    document.getElementById('plat-form-id').value = '';
    document.getElementById('plat-form-nom').value = '';
    document.getElementById('plat-form-type').value = '';
    // Décocher tous les allergènes
    document.querySelectorAll('#plat-form-allergenes input[type="checkbox"]').forEach(cb => cb.checked = false);

    document.getElementById('platFormModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nouveau plat';
    document.getElementById('plat-form-submit-btn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Créer le plat';

    const modal = new bootstrap.Modal(document.getElementById('platFormModal'));
    modal.show();
}

// --- Ouvrir la modale d'édition de plat ---
function openPlatEditModal(platId) {
    const plat = allPlatsDisplayData.find(p => p.id === platId);
    if (!plat) return;

    document.getElementById('plat-form-id').value = plat.id;
    document.getElementById('plat-form-nom').value = plat.nom || '';
    document.getElementById('plat-form-type').value = plat.type || '';

    // Cocher les allergènes du plat
    document.querySelectorAll('#plat-form-allergenes input[type="checkbox"]').forEach(cb => {
        cb.checked = (plat.allergenes || []).includes(cb.value);
    });

    document.getElementById('platFormModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Modifier le plat';
    document.getElementById('plat-form-submit-btn').innerHTML = '<i class="bi bi-check-circle me-1"></i>Enregistrer';

    const modal = new bootstrap.Modal(document.getElementById('platFormModal'));
    modal.show();
}

// --- Soumettre le formulaire plat ---
async function submitPlatForm() {
    const platId = document.getElementById('plat-form-id').value;
    const isEdit = platId !== '';

    const nom = document.getElementById('plat-form-nom').value.trim();
    const type = document.getElementById('plat-form-type').value;

    if (!nom || !type) {
        showEmployeError('Veuillez remplir le nom et le type du plat');
        return;
    }

    // Récupérer les allergènes cochés
    const allergeneCheckboxes = document.querySelectorAll('#plat-form-allergenes input[type="checkbox"]:checked');
    const allergenes = Array.from(allergeneCheckboxes).map(cb => cb.value);

    const data = { nom, type, allergenes };
    if (isEdit) data.id = parseInt(platId);

    const submitBtn = document.getElementById('plat-form-submit-btn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';

    try {
        const url = isEdit ? `${API_BASE_URL}/menus/plats/update.php` : `${API_BASE_URL}/menus/plats/create.php`;
        const method = isEdit ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: getAuthHeaders(),
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showEmployeSuccess(isEdit ? 'Plat modifié avec succès' : 'Plat créé avec succès');
            const modal = bootstrap.Modal.getInstance(document.getElementById('platFormModal'));
            if (modal) modal.hide();
            loadAllPlats();
            // Recharger aussi les menus pour mettre à jour les stats
            loadAllMenus();
        } else {
            showEmployeError(result.message || 'Erreur lors de l\'opération');
        }

    } catch (error) {
        console.error('Erreur soumission plat:', error);
        showEmployeError('Erreur de connexion au serveur');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = isEdit
            ? '<i class="bi bi-check-circle me-1"></i>Enregistrer'
            : '<i class="bi bi-check-circle me-1"></i>Créer le plat';
    }
}

// --- Suppression de plat (hard delete) ---
function openPlatDeleteModal(platId, platNom) {
    platToDeleteId = platId;
    document.getElementById('plat-delete-name').textContent = platNom;
    const modal = new bootstrap.Modal(document.getElementById('platDeleteModal'));
    modal.show();
}

async function confirmDeletePlat() {
    if (!platToDeleteId) return;

    const confirmBtn = document.getElementById('plat-delete-confirm-btn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>En cours...';

    try {
        const response = await fetch(`${API_BASE_URL}/menus/plats/delete.php?id=${platToDeleteId}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        const result = await response.json();

        if (result.success) {
            showEmployeSuccess(result.message || 'Plat supprimé avec succès');
            const modal = bootstrap.Modal.getInstance(document.getElementById('platDeleteModal'));
            if (modal) modal.hide();
            loadAllPlats();
            // Recharger les menus car les associations ont pu changer
            loadAllMenus();
        } else {
            showEmployeError(result.message || 'Erreur lors de la suppression');
        }

    } catch (error) {
        console.error('Erreur suppression plat:', error);
        showEmployeError('Erreur de connexion au serveur');
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="bi bi-trash me-1"></i>Supprimer définitivement';
        platToDeleteId = null;
    }
}
