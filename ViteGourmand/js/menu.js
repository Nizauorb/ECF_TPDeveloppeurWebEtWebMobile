// Données des menus
export const menuData = {
    classique: {
        title: "Menu Classique",
        price: "35€",
        image: "../img/Classique.png",
        description: "Un festin convivial et raffiné pour 2 à 6 convives. Savourez une terrine de campagne généreuse, un filet de bœuf Rossini accompagné d'un gratin dauphinois crémeux, et une crème brûlée à la vanille parfaitement caramélisée. Une expérience gastronomique élégante, parfaite pour un repas familial ou entre amis.",
        minPeople: "2-6",
        allergenes: ["Gluten", "Lait et produits laitiers", "Œufs"],
        conditionsCommande: "Commande à effectuer au minimum 3 jours avant la prestation. Conservation au réfrigérateur entre 0°C et 4°C.",
        regime: "Classique",
        stockDisponible: 12,
        sections: {
            entrees: [
                "Terrine de campagne au lard fumé et au Morbier, cornichons, pain de campagne toasté"
            ],
            plats: [
                "Filet de bœuf Rossini (foie gras poêlé, sauce madire), gratin dauphinois"
            ],
            desserts: [
                "Crème brûlée à la vanille de Madagascar"
            ]
        }
    },
    noel: {
        title: "Menu de Noël",
        price: "55€",
        image: "../img/Noel.png",
        description: "Un festin de Noël généreux pour 6 à 10 convives. Dégustez des huîtres fraîches en entrée, un chapon rôti farci aux châtaignes accompagné d'un gratin de pommes de terre au Reblochon fondant, et une bûche de Noël traditionnelle au chocolat et marron glacé. Une table scintillante aux couleurs de fête pour célébrer ensemble dans une ambiance chaleureuse et raffinée.",
        minPeople: "6-10",
        allergenes: ["Mollusques", "Lait et produits laitiers", "Fruits à coque", "Œufs", "Gluten"],
        conditionsCommande: "Commande à effectuer au minimum 2 semaines avant la prestation en raison de la disponibilité saisonnière des produits (huîtres fraîches, chapon). Les huîtres doivent être conservées vivantes au frais (5-10°C) et consommées rapidement. Le chapon nécessite une préparation spécifique.",
        regime: "Classique",
        stockDisponible: 5,
        sections: {
            entrees: [
                "Huîtres fines de claire n°2, mignonnette au vinaigre d'échalote"
            ],
            plats: [
                "Chapon rôti farci aux châtaignes, jus au fond brun, gratin de pommes de terre au Reblochon"
            ],
            desserts: [
                "Bûche de Noël traditionnelle au chocolat et marron glacé"
            ]
        }
    },
    paques: {
        title: "Menu de Pâques",
        price: "38€",
        image: "../img/Paques.png",
        description: "Un menu de Pâques automnal et réconfortant pour 4 à 6 convives. Dégustez un velouté de potimarron onctueux au foie gras poêlé, un jarret de bœuf braisé fondant accompagné d'une purée de céleri-rave au Comté et de carottes glacées, et une délicate tarte fine aux poires confites. Une ambiance sereine et chaleureuse aux couleurs automnales pour célébrer ce moment festif.",
        minPeople: "4-6",
        allergenes: ["Lait et produits laitiers", "Fruits à coque", "Gluten", "Œufs"],
        conditionsCommande: "Commande à effectuer au minimum 5 jours avant la prestation. Le jarret de bœuf nécessite un temps de préparation prolongé. Conservation au réfrigérateur entre 0°C et 4°C.",
        regime: "Classique",
        stockDisponible: 8,
        sections: {
            entrees: [
                "Velouté de potimarron au foie gras poêlé et croûtons de pain aux noix"
            ],
            plats: [
                "Jarret de bœuf braisé au vin rouge, purée de céleri-rave au Comté râpé, carottes glacées"
            ],
            desserts: [
                "Tarte fine aux poires confites et crème vanille"
            ]
        }
    },
    event: {
        title: "Menu d'Evénements",
        price: "48€",
        image: "../img/Event.png",
        description: "Un menu événementiel raffiné pour 10 convives et plus. Savourez des asperges vertes rôties aux œufs mollets mimosa, un carré d'agneau rôti aux herbes de Provence accompagné d'un risotto crémeux aux petits pois et menthe fraîche, et un fraisier revisité à la crème mousseline. Une table élégante et sophistiquée, parfaite pour célébrer vos grands événements dans une ambiance festive et raffinée.",
        minPeople: "10+",
        allergenes: ["Lait et produits laitiers", "Œufs", "Gluten"],
        conditionsCommande: "Commande à effectuer au minimum 3 semaines avant la prestation pour garantir la disponibilité des produits et la préparation soignée. Les asperges fraîches dépendent de la saison. Service traiteur sur place recommandé pour les groupes de plus de 15 personnes.",
        regime: "Classique",
        stockDisponible: 3,
        sections: {
            entrees: [
                "Asperges vertes rôties aux œufs mollets mimosa"
            ],
            plats: [
                "Carré d'agneau rôti aux herbes de Provence, risotto crémeux aux petits pois et menthe fraîche"
            ],
            desserts: [
                "Fraisier revisité – biscuit joconde, crème mousseline à la fraise, coulis de fruits rouges"
            ]
        }
    }
};

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
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="showMenuDetails('${menuKey}')">
                            <i class="bi bi-eye-fill"></i> Voir les détails
                        </button>
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
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-cart-plus me-2"></i>Commander ce menu
                        </button>
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
    console.log('Filtres appliqués -', filteredMenus.length, 'menus trouvés');
}

// Fonction pour réinitialiser les filtres
export function resetFilters() {
    document.getElementById('filterPeople').value = '';
    document.getElementById('filterRegime').value = '';
    document.getElementById('filterAllergenes').value = '';
    document.getElementById('filterStock').value = '';
    
    renderMenuCards();
    updateFilterResults(Object.keys(menuData).length);
    console.log('Filtres réinitialisés');
}

// Fonction pour obtenir les menus filtrés
export function getFilteredMenus() {
    const peopleFilter = document.getElementById('filterPeople').value;
    const regimeFilter = document.getElementById('filterRegime').value;
    const allergenesFilter = document.getElementById('filterAllergenes').value;
    const stockFilter = document.getElementById('filterStock').value;
    
    return Object.keys(menuData).filter(menuKey => {
        const menu = menuData[menuKey];
        
        // Filtre par nombre de personnes
        if (peopleFilter && menu.minPeople !== peopleFilter) {
            return false;
        }
        
        // Filtre par régime
        if (regimeFilter && menu.regime !== regimeFilter) {
            return false;
        }
        
        // Filtre par allergènes (exclusion)
        if (allergenesFilter && menu.allergenes.includes(allergenesFilter)) {
            return false;
        }
        
        // Filtre par stock
        if (stockFilter) {
            if (stockFilter === 'available' && menu.stockDisponible < 4) {
                return false;
            }
            if (stockFilter === 'limited' && menu.stockDisponible >= 4) {
                return false;
            }
        }
        
        return true;
    });
}

// Fonction pour mettre à jour le texte des résultats
export function updateFilterResults(count) {
    const resultsElement = document.getElementById('filterResults');
    const totalMenus = Object.keys(menuData).length;
    
    if (count === totalMenus) {
        resultsElement.textContent = `Affichage de tous les menus (${count})`;
    } else {
        resultsElement.textContent = `${count} menu${count > 1 ? 's' : ''} trouvé${count > 1 ? 's' : ''} sur ${totalMenus}`;
    }
}

// Initialisation quand le script est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('Menu.js chargé - Fonctionnalités prêtes');
});