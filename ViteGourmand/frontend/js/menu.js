// Données des menus — chargées dynamiquement depuis l'API
// Format : { menu_key: { title, price, image, description, minPeople, allergenes, conditionsCommande, regime, stockDisponible, sections } }
export let menuData = {};

// Fonction pour décoder les entités HTML
function decodeHtmlEntities(text) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

// Charger les menus depuis l'API backend
export async function loadMenusFromAPI() {
    try {
        const apiBaseUrl = window.API_BASE_URL || '/api';
        const fullUrl = `${apiBaseUrl}/menus/list.php`;

        console.log('🔍 API_BASE_URL:', window.API_BASE_URL);
        console.log('🔍 URL complète appelée:', fullUrl);
        console.log('🔍 URL finale:', window.location.origin + fullUrl);

        const response = await fetch(fullUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        console.log('🔍 Status HTTP:', response.status);
        console.log('🔍 Content-Type:', response.headers.get('content-type'));

        // LOG DE DIAGNOSTIC : réponse brute AVANT JSON.parse()
        const responseText = await response.text();
        console.log('🔍 Réponse brute (100 premiers caractères):', responseText.substring(0, 100));
        console.log('🔍 Début de la réponse:', responseText.startsWith('<') ? 'HTML' : 'JSON');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        // Tenter de parser le JSON seulement si c'est du JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('🔍 Erreur JSON.parse():', jsonError);
            console.error('🔍 Réponse qui a causé l\'erreur:', responseText.substring(0, 200));
            throw new Error('Réponse n\'est pas du JSON valide');
        }

        if (!result.success || !result.data) {
            console.error('Erreur chargement menus API:', result.message);
            return false;
        }

        // Transformer les données API au format attendu par les fonctions existantes
        const newMenuData = {};
        result.data.forEach(menu => {
            newMenuData[menu.menu_key] = {
                title: decodeHtmlEntities(menu.titre),
                price: `${parseFloat(menu.prix_par_personne).toFixed(0)}€`,
                image: menu.image || '',
                description: decodeHtmlEntities(menu.description || ''),
                minPeople: `${menu.nombre_personnes_min}+`,
                allergenes: menu.allergenes || [],
                conditionsCommande: decodeHtmlEntities(menu.conditions_commande || ''),
                regime: menu.regime || 'Classique',
                stockDisponible: menu.stock_disponible,
                sections: {
                    entrees: (menu.sections?.entrees || []).map(p => decodeHtmlEntities(p.nom)),
                    plats: (menu.sections?.plats || []).map(p => decodeHtmlEntities(p.nom)),
                    desserts: (menu.sections?.desserts || []).map(p => decodeHtmlEntities(p.nom))
                }
            };
        });

        menuData = newMenuData;
        console.log(`Menus chargés depuis l'API: ${Object.keys(menuData).length} menus`);
        return true;

    } catch (error) {
        console.error('Erreur fetch menus API:', error);
        return false;
    }
}

// Fonction pour afficher les cartes des menus
export function renderMenuCards(menusToRender = null) {
    const container = document.getElementById('menuContainer');
    if (!container) {
        console.log('Conteneur menuContainer non trouvé');
        return;
    }
    
    // Utiliser les menus filtrés ou tous les menus par défaut
    const menus = menusToRender || Object.keys(menuData);
    
    if (menus.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Aucun menu ne correspond à vos critères de filtrage.
                </div>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    menus.forEach(menuKey => {
        const menu = menuData[menuKey];
        
        html += `
            <div class="col">
                <div class="card h-100 menu-card mb-3">
                    <img src="${menu.image}" alt="${menu.title}" class="card-img-top img-fluid rounded-3">
                    <div class="card-body d-flex flex-column">
                        <h4 class="h5">${menu.title}</h4>
                        <p class="card-text flex-grow-1">${menu.description}</p>
                        <div class="menu-info mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary"><i class="bi bi-people-fill"></i> ${menu.minPeople} personnes</span>
                                <span class="fw-bold text-primary">${menu.price}/personne</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-success"><i class="bi bi-leaf-fill"></i> ${menu.regime}</span>
                                ${getStockBadge(menu.stockDisponible)}
                            </div>
                        </div>
                        ${menu.stockDisponible == 0 ? 
                            `<button class="btn btn-outline-secondary w-100 mt-auto" disabled>
                                <i class="bi bi-x-circle"></i> Stock épuisé
                            </button>` :
                            `<button class="btn btn-outline-primary w-100 mt-auto" onclick="showMenuDetails('${menuKey}')">
                                <i class="bi bi-eye-fill"></i> Voir les détails
                            </button>`
                        }
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    console.log('Cartes de menus générées avec succès');
}

// Fonction pour afficher les détails d'un menu
export function showMenuDetails(menuType) {
    const menu = menuData[menuType];
    if (!menu) return;

    // Créer le HTML pour les détails du menu
    const menuDetailsHTML = `
        <div class="modal fade" id="menuDetailsModal" tabindex="-1" aria-labelledby="menuDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="menuDetailsModalLabel">
                            <i class="bi bi-book-fill me-2"></i>${menu.title}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row align-items-center mb-4">
                            <div class="col-md-4 text-center">
                                <img src="${menu.image}" alt="${menu.title}" class="img-fluid rounded shadow mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-primary">
                                        <i class="bi bi-people-fill"></i> ${menu.minPeople} personnes
                                    </span>
                                    <span class="fw-bold text-primary">${menu.price}/personne</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <p class="text-muted">${menu.description}</p>
                            </div>
                        </div>
                        
                        <div class="menu-details-content">
                            ${generateMenuSections(menu.sections)}
                            ${generateAdditionalInfo(menu)}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                        ${menu.stockDisponible == 0 ? 
                            `<button type="button" class="btn btn-secondary" disabled>
                                <i class="bi bi-x-circle me-2"></i>Stock épuisé
                            </button>` :
                            `<button type="button" class="btn btn-primary" onclick="orderMenu('${menuType}')">
                                <i class="bi bi-cart-plus me-2"></i>Commander ce menu
                            </button>`
                        }
                    </div>
                </div>
            </div>
        </div>
    `;

    // Supprimer la modal existante si elle existe
    const existingModal = document.getElementById('menuDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Ajouter la modal au body
    document.body.insertAdjacentHTML('beforeend', menuDetailsHTML);

    // Afficher la modal
    const modal = new bootstrap.Modal(document.getElementById('menuDetailsModal'));
    modal.show();
}

// Fonction pour générer les sections du menu
export function generateMenuSections(sections) {
    let html = '';
    
    if (sections.entrees) {
        html += `
            <div class="menu-section mb-4">
                <h4 class="h5 fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="bi bi-egg-fried me-2"></i>Entrées
                </h4>
                <ul class="list-unstyled">
                    ${sections.entrees.map(item => `
                        <li class="py-2 d-flex align-items-center">
                            <i class="bi bi-arrow-right-circle-fill text-primary me-2"></i>
                            ${item}
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    }
    
    if (sections.plats) {
        html += `
            <div class="menu-section mb-4">
                <h4 class="h5 fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="bi bi-fire me-2"></i>Plats Principaux
                </h4>
                <ul class="list-unstyled">
                    ${sections.plats.map(item => `
                        <li class="py-2 d-flex align-items-center">
                            <i class="bi bi-arrow-right-circle-fill text-primary me-2"></i>
                            ${item}
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    }
    
    if (sections.desserts) {
        html += `
            <div class="menu-section">
                <h4 class="h5 fw-bold text-primary border-bottom pb-2 mb-3">
                    <i class="bi bi-cake2 me-2"></i>Desserts
                </h4>
                <ul class="list-unstyled">
                    ${sections.desserts.map(item => `
                        <li class="py-2 d-flex align-items-center">
                            <i class="bi bi-arrow-right-circle-fill text-primary me-2"></i>
                            ${item}
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    }
    
    return html;
}

// Fonction pour générer les informations complémentaires
export function generateAdditionalInfo(menu) {
    let html = `
        <div class="additional-info mt-4 pt-4 border-top">
            <h4 class="h5 fw-bold text-primary mb-3">
                <i class="bi bi-info-circle-fill me-2"></i>Informations complémentaires
            </h4>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="info-section">
                        <h5 class="h6 text-warning mb-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Allergènes
                        </h5>
                        <div class="d-flex flex-wrap gap-1">
                            ${menu.allergenes.map(allergene => `
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-alert-circle me-1"></i>${allergene}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="info-section">
                        <h5 class="h6 text-info mb-2">
                            <i class="bi bi-clock-fill me-1"></i>Conditions de commande
                        </h5>
                        <p class="small text-muted mb-0">${menu.conditionsCommande}</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="info-section">
                        <h5 class="h6 text-success mb-2">
                            <i class="bi bi-leaf-fill me-1"></i>Régime
                        </h5>
                        <span class="badge bg-success">${menu.regime}</span>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="info-section">
                        <h5 class="h6 text-primary mb-2">
                            <i class="bi bi-box-seam-fill me-1"></i>Stock disponible
                        </h5>
                        ${getStockBadge(menu.stockDisponible)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    return html;
}

// Fonction pour générer le badge de stock avec code couleur
export function getStockBadge(stock) {
    if (stock == 0) {
        return `<span class="badge bg-danger">
            <i class="bi bi-x-circle-fill me-1"></i>Épuisé
        </span>`;
    }
    
    let badgeClass = 'bg-danger';
    let icon = 'bi-x-circle-fill';
    
    if (stock >= 8) {
        badgeClass = 'bg-success';
        icon = 'bi-check-circle-fill';
    } else if (stock >= 4) {
        badgeClass = 'bg-warning';
        icon = 'bi-exclamation-circle-fill';
    }
    
    return `<span class="badge ${badgeClass}">
        <i class="bi ${icon} me-1"></i>${stock} commandes disponibles
    </span>`;
}

// Fonction pour appliquer les filtres
export function applyFilters() {
    const filteredMenus = getFilteredMenus();
    renderMenuCards(filteredMenus);
    updateFilterResults(filteredMenus.length);
    // DEBUG: Log de débogage - à supprimer en production
    console.log('Filtres appliqués -', filteredMenus.length, 'menus trouvés');
}

// Fonction pour réinitialiser les filtres
export function resetFilters() {
    document.getElementById('filterPeople').value = '';
    document.getElementById('filterRegime').value = '';
    document.getElementById('filterTheme').value = '';
    document.getElementById('filterPriceMin').value = '';
    document.getElementById('filterPriceMax').value = '';
    document.getElementById('filterAllergenes').value = '';
    
    renderMenuCards();
    updateFilterResults(Object.keys(menuData).length);
    // DEBUG: Log de débogage - à supprimer en production
    console.log('Filtres réinitialisés');
}

// Fonction pour obtenir les menus filtrés
export function getFilteredMenus() {
    const peopleFilter = document.getElementById('filterPeople').value;
    const regimeFilter = document.getElementById('filterRegime').value;
    const themeFilter = document.getElementById('filterTheme').value;
    const priceMinFilter = document.getElementById('filterPriceMin').value;
    const priceMaxFilter = document.getElementById('filterPriceMax').value;
    const allergenesFilter = document.getElementById('filterAllergenes').value;
    
    // DEBUG: Log de débogage - à supprimer en production
    console.log('Éléments de filtre trouvés:', {
        people: !!document.getElementById('filterPeople'),
        regime: !!document.getElementById('filterRegime'),
        theme: !!document.getElementById('filterTheme'),
        priceMin: !!document.getElementById('filterPriceMin'),
        priceMax: !!document.getElementById('filterPriceMax'),
        allergenes: !!document.getElementById('filterAllergenes')
    });
    
    return Object.keys(menuData).filter(menuKey => {
        const menu = menuData[menuKey];
        
        // Filtre par nombre de personnes (minimum)
        if (peopleFilter && parseInt(menu.minPeople) < parseInt(peopleFilter)) {
            return false;
        }
        
        // Filtre par régime
        if (regimeFilter && menu.regime !== regimeFilter) {
            return false;
        }
        
        // Filtre par thème
        if (themeFilter && menu.theme !== themeFilter) {
            return false;
        }
        
        // Filtre par prix (fourchette min-max)
        if (priceMinFilter || priceMaxFilter) {
            const price = parseFloat(menu.price);
            if (priceMinFilter && price < parseFloat(priceMinFilter)) {
                return false;
            }
            if (priceMaxFilter && price > parseFloat(priceMaxFilter)) {
                return false;
            }
        }
        
        // Filtre par allergènes (exclusion)
        if (allergenesFilter && menu.allergenes.includes(allergenesFilter)) {
            return false;
        }
        
        return true;
    });
}

// Fonction pour mettre à jour le texte des résultats
export function updateFilterResults(count) {
    const resultsElement = document.getElementById('filterResults');
    
    // DEBUG: Vérification de sécurité - à supprimer en production
    if (!resultsElement) {
        console.warn('DEBUG: Élément filterResults non trouvé - impossible d\'afficher les résultats');
        return;
    }
    
    const totalMenus = Object.keys(menuData).length;
    
    if (count === totalMenus) {
        resultsElement.textContent = `Affichage de tous les menus (${count})`;
    } else {
        resultsElement.textContent = `${count} menu${count > 1 ? 's' : ''} trouvé${count > 1 ? 's' : ''} sur ${totalMenus}`;
    }
}

// Fonction pour commander un menu (redirige vers la page de commande)
export function orderMenu(menuKey) {
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
        // Fermer la modale de détails si ouverte
        const detailsModal = document.getElementById('menuDetailsModal');
        if (detailsModal) {
            const bsDetailsModal = bootstrap.Modal.getInstance(detailsModal);
            if (bsDetailsModal) bsDetailsModal.hide();
        }
        // Afficher la modale de connexion requise
        showLoginRequiredModal();
        return;
    }
    
    // Fermer la modale et rediriger vers la page de commande avec le menu pré-sélectionné
    const modal = document.getElementById('menuDetailsModal');
    if (modal) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    }
    window.location.href = `/Commander?menu=${menuKey}`;
}

// Modale de connexion requise (stylisée)
function showLoginRequiredModal() {
    // Supprimer si déjà existante
    const existing = document.getElementById('loginRequiredModal');
    if (existing) existing.remove();

    const modalHTML = `
        <div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-labelledby="loginRequiredModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title" id="loginRequiredModalLabel">
                            <i class="bi bi-lock-fill me-2"></i>Connexion requise
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-person-circle" style="font-size: 3.5rem; color: #627D4A;"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Vous devez être connecté pour commander</h6>
                        <p class="text-muted mb-0">Connectez-vous à votre compte ou créez-en un pour passer commande et profiter de nos menus.</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                        <a href="/Login" class="btn btn-primary px-4">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                        </a>
                        <a href="/Register" class="btn btn-outline-primary px-4">
                            <i class="bi bi-person-plus me-1"></i>Créer un compte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    const modal = new bootstrap.Modal(document.getElementById('loginRequiredModal'));
    modal.show();

    // Nettoyage après fermeture
    document.getElementById('loginRequiredModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
}

// Initialisation quand le script est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('Menu.js chargé - Fonctionnalités prêtes'); // DEBUG: Log de débogage - à supprimer en production
    console.log('** DERNIER LOG DE DÉBOGAGE **'); // <--- MARQUER CE LOG DE DÉBOGAGE
});