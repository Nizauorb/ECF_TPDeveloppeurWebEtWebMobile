// frontend/js/admin-dashboard.js
// Script spécifique au dashboard admin — charge shared-dashboard.js pour les fonctions communes

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
// Authentification admin
// ============================================
function requireAdminAuth() {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
        window.location.href = '/Login';
        return false;
    }
    
    try {
        const user = JSON.parse(userStr);
        if (user.role !== 'administrateur') {
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
// Variables spécifiques admin
// ============================================
var currentAdmin = null;
var currentSection = 'commandes';

// HTML de la barre de filtres injecté dynamiquement dans .site-header
var adminFiltersHeaderHTML = `
<div class="employe-header-bar d-block d-xl-none" style="background-color: #8B0000; padding: 0.75rem 1rem;">
    <div class="d-flex justify-content-between align-items-center">
        <span class="text-white fw-semibold"><i class="bi bi-grid-3x3-gap me-2"></i>Commandes</span>
        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileFilters" aria-controls="adminMobileFilters">
            <i class="bi bi-funnel me-1"></i> Filtres
        </button>
    </div>
</div>
<div class="employe-header-bar d-none d-xl-block" style="background-color: #8B0000; padding: 0.85rem 0;">
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
<div class="offcanvas offcanvas-end" tabindex="-1" id="adminMobileFilters" aria-labelledby="adminMobileFiltersLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-semibold" id="adminMobileFiltersLabel">
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
    currentAdmin = requireAdminAuth();
    if (!currentAdmin) return;

    // Charger le script partagé
    await loadSharedDashboard();

    // Configurer le dashboard admin
    window.dashboardConfig = {
        sections: ['commandes', 'menus', 'horaires', 'avis', 'employes', 'profil'],
        titles: {
            'commandes': 'Les Commandes',
            'menus': 'Les Menus',
            'horaires': 'Les Horaires',
            'avis': 'Les Avis',
            'employes': 'Les Employ\u00e9s',
            'profil': 'Mon Profil'
        },
        titleElementId: 'admin-page-title',
        filtersHeaderHTML: adminFiltersHeaderHTML
    };

    loadCSRFToken();
    displayAdminInfo(currentAdmin);
    loadAllCommands();

    // Injecter la barre de filtres par défaut (section commandes)
    const siteHeader = document.querySelector('.site-header');
    if (siteHeader) siteHeader.innerHTML = adminFiltersHeaderHTML;

    // Lire le paramètre ?section= de l'URL pour afficher la bonne section
    const urlParams = new URLSearchParams(window.location.search);
    const sectionParam = urlParams.get('section');
    if (sectionParam) {
        const sectionMap = { 'orders': 'commandes', 'menus': 'menus', 'horaires': 'horaires', 'avis': 'avis', 'employes': 'employes', 'profile': 'profil' };
        const targetSection = sectionMap[sectionParam] || sectionParam;
        showSection(targetSection);
    }
})();

// ============================================
// Affichage infos admin (spécifique)
// ============================================
function displayAdminInfo(user) {
    const fullName = `${user.firstName || ''} ${user.lastName || ''}`.trim();
    
    const sidebarName = document.getElementById('sidebar-user-name');
    if (sidebarName) sidebarName.textContent = fullName;
    
    const sidebarNameMobile = document.getElementById('sidebar-user-name-mobile');
    if (sidebarNameMobile) sidebarNameMobile.textContent = fullName;
    
    // Profil
    const profilNom = document.getElementById('admin-profile-nom');
    if (profilNom) profilNom.value = user.lastName || '';
    const profilPrenom = document.getElementById('admin-profile-prenom');
    if (profilPrenom) profilPrenom.value = user.firstName || '';
    const profilEmail = document.getElementById('admin-profile-email');
    if (profilEmail) profilEmail.value = user.email || '';
    const profilTel = document.getElementById('admin-profile-telephone');
    if (profilTel) profilTel.value = user.phone || '';
}

// ============================================
// Gestion des employés (admin uniquement)
// ============================================

async function loadEmployesList() {
    const loading = document.getElementById('employes-loading');
    const empty = document.getElementById('employes-empty');
    const tableContainer = document.getElementById('employes-table-container');
    const tableBody = document.getElementById('employes-table-body');

    if (loading) loading.style.display = '';
    if (empty) empty.style.display = 'none';
    if (tableContainer) tableContainer.style.display = 'none';

    try {
        const response = await fetch(`${API_BASE_URL}/admin/list-employes.php`, {
            headers: getAuthHeaders()
        });
        const data = await response.json();

        if (loading) loading.style.display = 'none';

        if (!data.success || !data.data || data.data.length === 0) {
            if (empty) empty.style.display = '';
            const statTotal = document.getElementById('stat-employes-total');
            if (statTotal) statTotal.textContent = '0';
            return;
        }

        const employes = data.data;

        // Mettre à jour la stat
        const statTotal = document.getElementById('stat-employes-total');
        if (statTotal) statTotal.textContent = employes.length;

        // Remplir le tableau
        if (tableBody) {
            tableBody.innerHTML = employes.map(emp => {
                const createdDate = emp.created_at ? new Date(emp.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';
                return `
                    <tr>
                        <td>
                            <div class="fw-semibold">${escapeHtml(emp.last_name)} ${escapeHtml(emp.first_name)}</div>
                        </td>
                        <td><span class="text-muted">${escapeHtml(emp.email)}</span></td>
                        <td>${escapeHtml(emp.phone || '-')}</td>
                        <td><small class="text-muted">${createdDate}</small></td>
                        <td class="text-end">
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteEmploye(${emp.id}, '${escapeHtml(emp.first_name)} ${escapeHtml(emp.last_name)}')" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        if (tableContainer) tableContainer.style.display = '';

    } catch (error) {
        console.error('Erreur chargement employés:', error);
        if (loading) loading.style.display = 'none';
        if (empty) {
            empty.style.display = '';
            empty.querySelector('p').textContent = 'Erreur lors du chargement';
        }
    }
}

async function submitCreateEmploye() {
    const nom = document.getElementById('employe-form-nom')?.value.trim();
    const prenom = document.getElementById('employe-form-prenom')?.value.trim();
    const email = document.getElementById('employe-form-email')?.value.trim();
    const telephone = document.getElementById('employe-form-telephone')?.value.trim();

    if (!nom || !prenom || !email || !telephone) {
        showDashboardError('Veuillez remplir tous les champs obligatoires.');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/admin/create-employe.php`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                lastName: nom,
                firstName: prenom,
                email: email,
                phone: telephone
            })
        });

        const data = await response.json();

        if (!data.success) {
            showDashboardError(data.message || 'Erreur lors de la création');
            return;
        }

        // Afficher le résultat avec le mot de passe généré
        const resultDiv = document.getElementById('employe-creation-result');
        if (resultDiv) {
            resultDiv.classList.remove('d-none');
            document.getElementById('result-employe-email').textContent = data.data.employe.email;
            document.getElementById('result-employe-password').textContent = data.data.generatedPassword;
            const emailStatus = document.getElementById('result-email-status');
            if (emailStatus) {
                emailStatus.textContent = data.data.emailSent
                    ? 'Un email avec les identifiants a été envoyé à l\'employé.'
                    : 'L\'email n\'a pas pu être envoyé. Transmettez les identifiants manuellement.';
            }
        }

        // Réinitialiser le formulaire
        document.getElementById('createEmployeForm')?.reset();

        showDashboardSuccess('Compte employé créé avec succès !');

        // Recharger la liste
        loadEmployesList();

    } catch (error) {
        console.error('Erreur création employé:', error);
        showDashboardError('Erreur réseau lors de la création du compte.');
    }
}

async function deleteEmploye(employeId, employeName) {
    const confirmed = await confirmAction(
        'Supprimer cet employé ?',
        `Êtes-vous sûr de vouloir supprimer le compte de <strong>${employeName}</strong> ? Cette action est irréversible.`,
        'Supprimer',
        'btn-danger'
    );

    if (!confirmed) return;

    try {
        const response = await fetch(`${API_BASE_URL}/admin/delete-employe.php?id=${employeId}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        const data = await response.json();

        if (!data.success) {
            showDashboardError(data.message || 'Erreur lors de la suppression');
            return;
        }

        showDashboardSuccess('Compte employé supprimé avec succès.');
        loadEmployesList();

    } catch (error) {
        console.error('Erreur suppression employé:', error);
        showDashboardError('Erreur réseau lors de la suppression.');
    }
}

// Utilitaire d'échappement HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
