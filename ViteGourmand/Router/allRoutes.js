import Route from "./Route.js";

//Définir ici vos routes
export const allRoutes = [
    new Route("/", "Accueil", "/pages/home.html"),
    new Route("/Galerie", "Galerie", "/pages/galerie.html"),
    new Route("/Carte", "Carte", "/pages/menu.html", "/js/menu.js", "/headers/menu-header.html"),
    new Route("/Contact", "Contact", "/pages/contact.html"),
    new Route("/Login", "Login", "/pages/404.html"),
    new Route("/Register", "Register", "/pages/404.html")
];

//Le titre s'affiche comme ceci : Route.titre - websitename
export const websiteName = "Vite&Gourmand";