// Données des menus
const menuData = {
    classique: {
        title: "Menu Classique",
        price: "42€",
        image: "../img/Classique.png",
        description: "Un festin convivial et raffiné pour 2 à 6 convives. Savourez une terrine de campagne généreuse, un filet de bœuf Rossini accompagné d'un gratin dauphinois crémeux, et une crème brûlée à la vanille parfaitement caramélisée. Une expérience gastronomique élégante, parfaite pour un repas familial ou entre amis.",
        minPeople: "2-6",
        sections: {
            entrees: [
                "Soupe aux champignons onctueuse",
                "Rouleaux de printemps croustillants", 
                "Raviolis vapeur aux légumes"
            ],
            plats: [
                "Morceaux de viande caramélisée",
                "Légumes verts sautés",
                "Dim sum vapeur assortis (raviolis et petits pains)"
            ],
            desserts: [
                "Melon frais",
                "Fruit du dragon",
                "Cerises sucrées"
            ]
        }
    }
};

// Fonction pour afficher les détails d'un menu
function showMenuDetails(menuType) {
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
function generateMenuSections(sections) {
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

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    console.log('Menu.js chargé - Fonctionnalités prêtes');
});