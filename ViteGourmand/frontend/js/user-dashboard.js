// frontend/js/user-dashboard.js
function requireAuth() {
    const auth = new AuthValidator();
    
    if (!auth.isLoggedIn()) {
        window.location.href = '/Login';
        return false;
    }
    
    return auth.getCurrentUser();
}

// Exemple d'utilisation dans une page utilisateur
document.addEventListener('DOMContentLoaded', () => {
    const user = requireAuth();
    if (user) {
        console.log('Utilisateur connecté:', user);
        
        // Charger les commandes de l'utilisateur
        loadUserCommands(user.id);
        
        // Afficher les infos personnelles
        displayUserInfo(user);
        
        // Mettre en place le bouton de déconnexion
        setupLogoutButton();
    }
});

// Fonctions spécifiques à la page utilisateur
async function loadUserCommands(userId) {
    try {
        const response = await fetch(`/api/commands/user/${userId}`);
        const commands = await response.json();
        
        // Afficher les commandes dans le tableau
        displayCommands(commands.data);
    } catch (error) {
        console.error('Erreur:', error);
    }
}

function displayUserInfo(user) {
    document.getElementById('user-name').textContent = `${user.prenom} ${user.nom}`;
    document.getElementById('user-email').textContent = user.email;
}

function setupLogoutButton() {
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            const auth = new AuthValidator();
            auth.logout();
        });
    }
}