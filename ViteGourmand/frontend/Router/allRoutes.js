import Route from "./Route.js";

//Définir ici vos routes
export const allRoutes = [
    new Route("/", "Accueil", "/pages/home.html"),
    new Route("/Galerie", "Galerie", "/pages/galerie.html"),
    new Route("/Carte", "Carte", "/pages/menu.html", "/js/menu.js", "/headers/menu-header.html"),
    new Route("/Contact", "Contact", "/pages/contact.html", "/js/contact.js"),
    new Route("/Login", "Login", "/pages/login.html", "/js/auth.js"),
    new Route("/Register", "Register", "/pages/register.html", "/js/auth.js"),
    new Route("/ForgotPassword", "ForgotPassword", "/pages/forgot-password.html", "/js/auth.js"),
    new Route("/ResetPassword", "ResetPassword", "/pages/reset-password.html", "/js/auth.js"),
    new Route("/UserDashboard", "UserDashboard", "/pages/user-dashboard.html", "/js/user-dashboard.js"),
];

//Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "Vite&Gourmand";