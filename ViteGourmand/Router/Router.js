import Route from "./Route.js";
import { allRoutes, websiteName } from "./allRoutes.js";

// Création d'une route pour la page 404 (page introuvable)
const route404 = new Route("404", "Page introuvable", "/pages/404.html");

// Fonction pour récupérer la route correspondant à une URL donnée
const getRouteByUrl = (url) => {
  let currentRoute = null;
  // Parcours de toutes les routes pour trouver la correspondance
  allRoutes.forEach((element) => {
    if (element.url == url) {
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
  const path = window.location.pathname;
  // Récupération de l'URL actuelle
  const actualRoute = getRouteByUrl(path);
  // Récupération du contenu HTML de la route
  const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
  // Ajout du contenu HTML à l'élément avec l'ID "main-page"
  document.getElementById("main-content").innerHTML = html;

  // Gestion du header spécifique si défini
  if (actualRoute.headerHtml) {
    const headerContent = await fetch(actualRoute.headerHtml).then((data) => data.text());
    document.querySelector(".site-header").innerHTML = headerContent;
  } else {
    // Header par défaut
    document.querySelector(".site-header").innerHTML = `
      <div class="header-container">
        <a href="/" class="logo-link">
          <img src="img/logo_header.png" alt="Logo Vite & Gourmand" class="rounded-4 logo-img">
        </a>
      </div>
    `;
  }

  // Gestion du JavaScript spécifique
  if (actualRoute.pathJS && actualRoute.pathJS.includes("menu.js")) {
    // Pour la page menu, utiliser le menuManager
    if (!window.menuManager) {
      // Charger le menuManager si pas encore chargé
      try {
        const module = await import(actualRoute.pathJS.replace('menu.js', 'menuManager.js'));
        console.log('MenuManager chargé');
      } catch (error) {
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
      console.log('Script chargé:', actualRoute.pathJS);
      document.title = actualRoute.title + " - " + websiteName;
    };

    scriptTag.onerror = function() {
      console.error('Erreur de chargement du script:', actualRoute.pathJS);
      document.title = actualRoute.title + " - " + websiteName;
    };

    document.querySelector("body").appendChild(scriptTag);
  } else {
    // Changer le titre de la page s'il n'y a pas de script
    document.title = actualRoute.title + " - " + websiteName;
  }
};

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