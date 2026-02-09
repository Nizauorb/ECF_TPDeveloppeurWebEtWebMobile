// frontend/js/menuManager.js

// Gestionnaire centralisé pour les fonctionnalités des menus
import { menuData, renderMenuCards, applyFilters, resetFilters, showMenuDetails, updateFilterResults, orderMenu } from './menu.js';

function requireAuth() {
    const auth = new AuthValidator();
    
    if (!auth.isLoggedIn()) {
        window.location.href = '/Login';
        return false;
    }
    
    return auth.getCurrentUser();
}

class MenuManager {
    constructor() {
        this.isInitialized = false;
    }

    // Fonction d'initialisation unique
    async initMenu() {
        if (this.isInitialized) {
            // DEBUG: Log de débogage - à supprimer en production
            console.log('MenuManager déjà initialisé');
            return;
        }

        // DEBUG: Log de débogage - à supprimer en production
        console.log('Initialisation du MenuManager...');
        
        // Attendre que le DOM soit chargé
        if (document.readyState === 'loading') {
            await new Promise(resolve => {
                document.addEventListener('DOMContentLoaded', resolve);
            });
        }

        // Rendre les fonctions accessibles globalement pour les onclick
        window.applyFilters = applyFilters;
        window.resetFilters = resetFilters;
        window.showMenuDetails = showMenuDetails;
        window.orderMenu = orderMenu;

        // Initialiser l'affichage des menus
        renderMenuCards();
        
        this.isInitialized = true;
        // DEBUG: Log de débogage - à supprimer en production
        console.log('MenuManager initialisé avec succès');
    }

    // Réinitialiser l'état pour les tests
    reset() {
        this.isInitialized = false;
    }
}

// Créer une instance globale du MenuManager
window.menuManager = new MenuManager();

// Exporter l'instance pour utilisation dans d'autres modules
export default window.menuManager;
