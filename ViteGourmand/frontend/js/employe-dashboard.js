// frontend/js/employe-dashboard.js
// Script spécifique au dashboard employé — charge shared-dashboard.js pour les fonctions communes

// Prise de contrôle immédiate du header et du footer (layout plein écran)
(function() {
    const h = document.querySelector('.site-header');
    if (h) h.innerHTML = '';
    const f = document.querySelector('footer');
    if (f) f.style.display = 'none';
})();

// ============================================
// Chargement dynamique de shared-dashboard.js
// ============================================
function loadSharedDashboard() {
    return new Promise((resolve, reject) => {
        if (typeof showSection === 'function') {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = '/js/shared-dashboard.js';
        script.onload = resolve;
        script.onerror = () => reject(new Error('Impossible de charger shared-dashboard.js'));
        document.head.appendChild(script);
    });
}

// ============================================
// Authentification employé
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

// ============================================
// Variables spécifiques employé
// ============================================
var currentEmploye = null;
var currentSection = 'commandes';

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
// Initialisation
// ============================================
(async function() {
    currentEmploye = requireEmployeAuth();
    if (!currentEmploye) return;

    // Charger le script partagé
    await loadSharedDashboard();

    // Configurer le dashboard employé
    window.dashboardConfig = {
        sections: ['commandes', 'menus', 'horaires', 'avis', 'profil'],
        titles: {
            'commandes': 'Les Commandes',
            'menus': 'Les Menus',
            'horaires': 'Les Horaires',
            'avis': 'Les Avis',
            'profil': 'Mon Profil'
        },
        titleElementId: 'employe-page-title',
        filtersHeaderHTML: employeFiltersHeaderHTML
    };

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
})();

// ============================================
// Affichage infos employé (spécifique)
// ============================================
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
