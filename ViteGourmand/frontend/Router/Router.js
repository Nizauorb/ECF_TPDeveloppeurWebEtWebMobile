import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";

// Création d'une route pour la page 404 (page introuvable)
const route404 = new Route("404", "Page introuvable", "/pages/404.html");

// Retourne l'URL du dashboard selon le rôle de l'utilisateur
const getDashboardUrlByRole = (role) => {
  switch (role) {
    case 'administrateur': return '/admin';
    case 'employe': return '/EmployeDashboard';
    default: return '/UserDashboard';
  }
};

// Retourne les éléments de navigation de l'offcanvas selon le rôle
const getNavItemsByRole = (role, dashboardUrl) => {
  const items = [];

  if (role === 'employe' || role === 'administrateur') {
    items.push(
      { href: dashboardUrl + '?section=orders', icon: 'bi-clipboard-check', label: 'Les Commandes' },
      { href: dashboardUrl + '?section=menus', icon: 'bi-book', label: 'Les Menus' },
      { href: dashboardUrl + '?section=horaires', icon: 'bi-clock', label: 'Les Horaires' },
      { href: dashboardUrl + '?section=avis', icon: 'bi-chat-dots', label: 'Les Avis' },
      { href: dashboardUrl + '?section=profile', icon: 'bi-person-gear', label: 'Mon Profil' }
    );
  } else {
    items.push(
      { href: dashboardUrl + '?section=orders', icon: 'bi-bag-check', label: 'Mes Commandes' },
      { href: dashboardUrl + '?section=profile', icon: 'bi-person-gear', label: 'Mon Profil' }
    );
  }

  return items;
};

// Mise à jour de l'interface selon l'état de connexion
const updateAuthUI = () => {
  const token = localStorage.getItem('token');
  const userStr = localStorage.getItem('user');
  const isLoggedIn = token && userStr;

  // Boutons desktop (.auth-actions)
  const authDesktop = document.querySelectorAll('.auth-actions');
  // Boutons mobile (.auth-actions-mobile)
  const authMobile = document.querySelectorAll('.auth-actions-mobile');

  if (isLoggedIn) {
    const user = JSON.parse(userStr);

    // Desktop : bouton "Mon Profil" qui ouvre l'offcanvas
    authDesktop.forEach(el => {
      el.innerHTML = `
        <button class="btn btn-primary login-btn" data-bs-toggle="offcanvas" data-bs-target="#userProfileMenu">
          <i class="bi bi-person-circle me-1"></i> ${user.firstName || 'Mon Profil'}
        </button>
      `;
    });

    // Mobile : bouton "Mon Profil" + Déconnexion
    authMobile.forEach(el => {
      el.innerHTML = `
        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="offcanvas" data-bs-target="#userProfileMenu">
          <i class="bi bi-person-circle me-2"></i> Mon Profil
        </button>
        <button class="btn btn-outline-danger w-100" onclick="localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href='/Login';">
          <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
        </button>
      `;
    });

    // Offcanvas profil : remplir l'email et le nom
    const offcanvasEmail = document.getElementById('offcanvas-user-email');
    if (offcanvasEmail) offcanvasEmail.textContent = user.email || '';
    const offcanvasName = document.getElementById('userProfileMenuLabel');
    if (offcanvasName) offcanvasName.textContent = `${user.firstName || ''} ${user.lastName || ''}`;

    // Générer dynamiquement le contenu de l'offcanvas selon le rôle
    const navBody = document.getElementById('offcanvas-nav-body');
    if (navBody) {
      const dashboardUrl = getDashboardUrlByRole(user.role);
      const navItems = getNavItemsByRole(user.role, dashboardUrl);
      navBody.innerHTML = `
        <nav class="py-2">
          <ul class="list-unstyled mb-0">
            ${navItems.map(item => `
              <li>
                <a href="${item.href}" class="d-flex align-items-center px-4 py-3 text-decoration-none text-dark">
                  <i class="bi ${item.icon} me-3 fs-5 text-primary"></i>
                  <span>${item.label}</span>
                </a>
              </li>
            `).join('')}
          </ul>
        </nav>
        <hr class="my-0">
        <div class="p-3">
          <a href="/Carte" class="btn btn-outline-primary w-100 mb-2">
            <i class="bi bi-plus-circle me-2"></i>Nouvelle Commande
          </a>
          <button class="btn btn-outline-danger w-100" id="offcanvas-logout-btn">
            <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
          </button>
        </div>
      `;
    }

    // Bouton déconnexion dans l'offcanvas
    const logoutBtn = document.getElementById('offcanvas-logout-btn');
    if (logoutBtn) {
      logoutBtn.onclick = () => {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/Login';
      };
    }
  } else {
    // Non connecté : bouton "Connexion"
    authDesktop.forEach(el => {
      el.innerHTML = `
        <a href="/Login" class="btn btn-primary login-btn">
          <i class="bi bi-person"></i> Connexion
        </a>
      `;
    });

    authMobile.forEach(el => {
      el.innerHTML = `
        <a href="/Login" class="btn btn-primary login-btn w-100">
          <i class="bi bi-person me-2"></i> Connexion
        </a>
      `;
    });
  }
};

// Fonction pour récupérer la route correspondant à une URL donnée
const getRouteByUrl = (url) => {
  let currentRoute = null;
  // Enlever les paramètres d'URL pour la correspondance des routes
  const path = url.split('?')[0];
  // Parcours de toutes les routes pour trouver la correspondance
  allRoutes.forEach((element) => {
    if (element.url === path) {
      currentRoute = element;
    }
  });
  // Si aucune correspondance n'est trouvée, on retourne la route 404
  if (currentRoute != null) {
    return currentRoute;
  } else {
    return route404;
  }
};

// Fonction pour charger le contenu de la page
const LoadContentPage = async () => {
  const path = window.location.pathname + window.location.search;
  // Récupération de l'URL actuelle
  const actualRoute = getRouteByUrl(path);
  console.log('Route trouvée:', actualRoute); // Debug
  
  // Récupération du contenu HTML de la route
  const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
  // Ajout du contenu HTML à l'élément avec l'ID "main-content"
  document.getElementById("main-content").innerHTML = html;

  // Extraire les paramètres d'URL
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');
  
  // Stocker le token dans le localStorage pour une utilisation ultérieure
  if (token) {
    localStorage.setItem('resetToken', token);
  }

  // Gestion du header spécifique si défini
  if (actualRoute.headerHtml) {
    const headerContent = await fetch(actualRoute.headerHtml).then((data) => data.text());
    document.querySelector(".site-header").innerHTML = headerContent;
  } else {
    // Header par défaut
    document.querySelector(".site-header").innerHTML = `
      <div class="default-header-wrapper">
        <a href="/">
          <img src="/mini_logo_header.png" alt="Logo Vite & Gourmand" class="default-logo">
        </a>
      </div>
    `;
  }

  // Mettre à jour l'interface d'authentification après injection du header
  updateAuthUI();

  // Gestion du JavaScript spécifique
  if (actualRoute.pathJS && actualRoute.pathJS.includes("menu.js")) {
    // Pour la page menu, utiliser le menuManager
    if (!window.menuManager) {
      // Charger le menuManager si pas encore chargé
      try {
        const module = await import(/* @vite-ignore */ actualRoute.pathJS.replace('menu.js', 'menuManager.js'));
        // DEBUG: Log de débogage - à supprimer en production
      console.log('MenuManager chargé');
      } catch (error) {
        // DEBUG: Log de débogage - à supprimer en production
      console.error('Erreur de chargement du MenuManager:', error);
      }
    }
    
    // Initialiser le menu
    if (window.menuManager) {
      window.menuManager.initMenu();
    }
  } else if (actualRoute.pathJS && actualRoute.pathJS !== "") {
    // Pour les autres scripts, utiliser l'ancienne méthode
    var scriptTag = document.createElement("script");
    scriptTag.setAttribute("type", "text/javascript");
    scriptTag.setAttribute("src", actualRoute.pathJS);

    scriptTag.onload = function() {
      // DEBUG: Log de débogage - à supprimer en production
      console.log('Script chargé:', actualRoute.pathJS);
      document.title = actualRoute.title + " - " + websiteName;
    };

    scriptTag.onerror = function() {
      // DEBUG: Log de débogage - à supprimer en production
      console.error('Erreur de chargement du script:', actualRoute.pathJS);
      document.title = actualRoute.title + " - " + websiteName;
    };

    document.querySelector("body").appendChild(scriptTag);
  } else {
    // Changer le titre de la page s'il n'y a pas de script
    document.title = actualRoute.title + " - " + websiteName;
  }
};

// Exposer updateAuthUI globalement pour pouvoir l'appeler après déconnexion
window.updateAuthUI = updateAuthUI;

// Fonction pour gérer les événements de routage (clic sur les liens)
const routeEvent = (event) => {
  event = event || window.event;
  event.preventDefault();
  // Mise à jour de l'URL dans l'historique du navigateur
  window.history.pushState({}, "", event.target.href);
  // Chargement du contenu de la nouvelle page
  LoadContentPage();
};

// Gestion de l'événement de retour en arrière dans l'historique du navigateur
window.onpopstate = LoadContentPage;
// Assignation de la fonction routeEvent à la propriété route de la fenêtre
window.route = routeEvent;
// Chargement du contenu de la page au chargement initial
LoadContentPage();