import Route from "./Route.js";

//Définir ici vos routes
export const allRoutes = [
    new Route("/", "Accueil", "/pages/home.html"),
    new Route("/Galerie", "Galerie", "/pages/galerie.html"),
    new Route("/Carte", "Carte", "/pages/menu.html", "/js/menu.js", "/headers/menu-header.html"),
    new Route("/Contact", "Contact", "/pages/contact.html", "/js/contact.js"),
    new Route("/Login", "Connexion", "/pages/login.html", "/js/auth.js"),
    new Route("/Register", "Inscription", "/pages/register.html", "/js/auth.js"),
    new Route("/ForgotPassword", "Mot de passe oublié", "/pages/forgot-password.html", "/js/auth.js"),
    new Route("/ResetPassword", "Réinitialisation du mot de passe", "/pages/reset-password.html", "/js/auth.js"),
    new Route("/UserDashboard", "Espace Client", "/pages/user-dashboard.html", "/js/user-dashboard.js"),
    new Route("/Commander", "Commander", "/pages/order.html", "/js/order.js"),
    new Route("/EmployeDashboard", "Espace Employé", "/pages/employe-dashboard.html", "/js/employe-dashboard.js", "/headers/employe-header.html"),
];

//Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "Vite&Gourmand";