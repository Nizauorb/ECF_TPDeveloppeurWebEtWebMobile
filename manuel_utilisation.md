# Manuel d'Utilisation - Vite&Gourmand

## 1. Introduction

**Vite&Gourmand** est une plateforme web de commande de menus traiteur pour événements. Développée dans le cadre d'un ECF Développeur Web & Web Mobile, cette application permet aux utilisateurs de découvrir, commander et gérer des menus adaptés à différentes occasions (classique, Noël, Pâques, événements).

L'application est accessible à l'adresse : https://vite-gourmand.maxime-brouazin.fr

Elle comprend quatre niveaux d'utilisateurs :
- **Visiteur** : Navigation anonyme.
- **Client** : Inscription/connexion pour passer des commandes.
- **Employé** : Gestion des commandes et horaires.
- **Administrateur** : Gestion complète des utilisateurs et statistiques.

## 2. Rôles Utilisateurs et Fonctionnalités

### Visiteur
- Consultation du catalogue de menus.
- Filtrage par prix, nombre de personnes, régime, thème, allergènes.
- Consultation des détails de menu.
- Formulaire de contact.
- Accès aux pages légales (Mentions légales, CGV).

### Client
- Inscription et connexion sécurisée.
- Gestion du profil personnel.
- Passage de commandes avec calcul automatique des frais.
- Suivi des commandes en temps réel.
- Historique des commandes.
- Dépôt d'avis sur les commandes terminées.
- Réinitialisation de mot de passe.
- Suppression de compte (RGPD).

### Employé
- Dashboard de gestion des commandes.
- Acceptation/refus de commandes avec motifs.
- Mise à jour des statuts de livraison.
- Contact direct avec les clients.
- Gestion des horaires du restaurant.
- Modification du profil personnel.

### Administrateur
- Toutes les permissions employé.
- Gestion complète des employés (CRUD).
- Attribution des rôles (employé/administrateur).
- Statistiques détaillées des commandes.
- Graphiques de performance par période/menu.
- Paramètres système globaux.

## 3. Identifiants de Test

- **Administrateur (José Martin)** :
  - Email : jose.martin@vite-gourmand.fr
  - Mot de passe : Admin1234!

- **Employé** :
  - Email : employe@vite-gourmand.fr
  - Mot de passe : Employe123!

- **Client** :
  - Email : client@vite-gourmand.fr
  - Mot de passe : Client123!

## 4. Parcours Visiteur

### Découverte du Catalogue
1. Accédez à la page d'accueil.
2. Cliquez sur "Menus" ou naviguez vers la section Catalogue.
3. Utilisez les filtres : prix (ex. 20-45€), nombre de personnes (ex. 4+), régime (Végétarien), thème (Noël), allergènes (sans gluten).
4. Cliquez sur un menu pour voir les détails (description, plats, conditions).

### Contact
1. Depuis le footer, cliquez sur "Contact".
2. Remplissez le formulaire avec nom, email, sujet, message.
3. Soumettez ; un email sera envoyé automatiquement.

### Pages Légales
1. Depuis le footer, accédez à "Mentions légales" ou "CGV".
2. Lisez les informations conformes à la RGPD.

*(Capture d'écran : Page 'Les-Menus.png' avec filtres actifs.)*

## 5. Parcours Client

### Inscription
1. Cliquez sur "S'inscrire" dans la barre de navigation.
2. Remplissez le formulaire : nom, prénom, email, téléphone, mot de passe.
3. Acceptez les CGV et validez.
4. Un email de confirmation est envoyé.

### Connexion
1. Cliquez sur "Se connecter".
2. Entrez email et mot de passe.
3. Accédez au dashboard client.

### Passage de Commande
1. Depuis le catalogue, sélectionnez un menu.
2. Choisissez la date/heure de prestation.
3. Remplissez les informations de livraison qui manquent (Date et heure de livraison).
4. Calcul automatique du total (prix menu + frais livraison 5€).
5. Confirmez ; statut initial : "En attente".

### Suivi de Commande
1. Dans le dashboard, consultez l'historique.
2. Cliquez sur une commande pour voir le statut (En attente → Acceptée → En préparation → Livrée).
3. Recevez des emails à chaque changement.

### Dépôt d'Avis
1. Après livraison, cliquez sur "Laisser un avis" pour la commande, vous recevrez un email pour les avis.
2. Notez (1-5 étoiles) et commentez.
3. Soumettez ; l'avis sera validé par un employé avant publication.

### Gestion du Profil
1. Dans le dashboard, cliquez sur "Mon profil".
2. Modifiez nom, email (avec vérification), téléphone.
3. Changez mot de passe via "Réinitialiser". Vous recevrez un email pour réinitialiser votre mot de passe.

### Suppression de Compte
1. Dans le profil, cliquez sur "Supprimer mon compte".
2. Confirmez avec code envoyé par email.
3. Le compte est anonymisé (RGPD).

*(Capture d'écran : Page 'Mes-Commandes.png' Dashboard client avec historique des commandes.)*

## 6. Parcours Employé

### Connexion et Dashboard
1. Connectez-vous avec les identifiants employé.
2. Accédez au dashboard des commandes.

### Gestion des Commandes
1. Consultez la liste des commandes (filtrez par statut).
2. Pour une commande "En attente" : Acceptez ou refusez avec motif.
3. Mettez à jour le statut (En préparation, En livraison, Livrée).
4. Contactez le client via email ou mobile avant d'annuler une commande.

### Gestion des Horaires
1. Dans le menu, cliquez sur "Horaires".
2. Modifiez les créneaux par jour (matin/soir, ouvert/fermé).
3. Sauvegardez ; les changements sont visibles publiquement sur la page d'accueil dans le footer.

### Gestion des Avis
1. Dans le menu, cliquez sur "Les avis".
2. Consultez les avis soumis par les clients.
3. Validez ou Refusez les avis. Ils apparaitront sur le site une fois validés.

### Profil
1. Modifiez vos informations personnelles.

*(Capture d'écran : Page 'Les-Avis.png' Dashboard employé avec liste des avis.)*

## 7. Parcours Administrateur

### Gestion des Employés
1. Dans le menu admin, cliquez sur "Les employés".
2. Consultez la liste des employés.
3. Créez/modifiez/supprimez des comptes.

### Statistiques
1. Accédez à "Statistiques".
2. Visualisez graphiques : commandes par période, par menu, revenus.
3. Exportez si nécessaire.

Toutes les actions employé sont également disponibles.

*(Capture d'écran : Page 'Statistiques.png' avec graphiques.)*

## 8. Annexes

### Installation en Local
- Clonez le repo : `git clone https://github.com/Nizauorb/Vite-Gourmand.git`
- Suivez le README pour configurer XAMPP, base de données, etc.
- Lancez `npm run dev` pour le frontend, accédez via localhost.
- Utilisateur de test hardcoded pour plus de facilité.

### Technologies
- Frontend : JavaScript ES6+, Bootstrap, Vite.
- Backend : PHP 8.1+, MySQL, JWT.
- Emails : PHPMailer (local) / Resend (prod).
