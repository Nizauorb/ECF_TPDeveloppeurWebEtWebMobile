# 🍽️ Vite&Gourmand - ECF Développeur Web & Web Mobile

[![License: ISC](https://img.shields.io/badge/License-ISC-blue.svg)](https://opensource.org/licenses/ISC)
[![PHP Version](https://img.shields.io/badge/PHP-8.1+-777BB4)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1)](https://mysql.com)

Plateforme de commande de menus traiteur pour événements - Projet ECF Développeur Web & Web Mobile

## 📋 Table des Matières

- [À propos](#-à-propos)
- [Fonctionnalités](#-fonctionnalités)
- [Technologies Utilisées](#-technologies-utilisées)
- [Structure du Projet](#-structure-du-projet)
- [Installation & Configuration](#-installation--configuration)
  - [Environnement de Développement (Local)](#environnement-de-développement-local)
  - [Environnement de Production](#environnement-de-production)
- [Utilisation](#-utilisation)
- [API Documentation](#-api-documentation)
- [Base de Données](#-base-de-données)
- [Déploiement](#-déploiement)
- [Tests](#-tests)
- [Contribuer](#-contribuer)
- [Auteur](#-auteur)
- [Licence](#-licence)

---

## 🎯 À propos

**Vite&Gourmand** est une plateforme web complète permettant aux utilisateurs de commander des menus traiteur pour leurs événements. Développée dans le cadre de l'Évaluation des Compétences en Cours de Formation (ECF) pour le titre Développeur Web & Web Mobile, cette application offre une expérience utilisateur fluide avec trois niveaux d'utilisateurs distincts.

### 🎓 Contexte ECF
- **Formation** : Développeur Web & Web Mobile
- **Établissement** : STUDI
- **Session** : 2025/2026
- **Compétences évaluées** :
  - Maquetter une application
  - Réaliser une interface utilisateur web statique et adaptable
  - Développer une interface utilisateur web dynamique
  - Réaliser une interface utilisateur avec une solution de gestion de contenu
  - Créer une base de données
  - Développer les composants d'accès aux données
  - Développer la partie back-end d'une application web
  - Élaborer et mettre en œuvre des composants dans une application de gestion de contenu

---

## ✨ Fonctionnalités

### 👤 Utilisateur Visiteur (Non connecté)
- ✅ Consultation du catalogue de menus
- ✅ Filtrage avancé (prix, nombre de personnes, régime, thème, allergènes)
- ✅ Consultation des détails de menu
- ✅ Prise de contact via formulaire
- ✅ Consultation des pages légales (Mentions légales, CGV)

### 👥 Utilisateur Client (Connecté)
- ✅ Inscription et connexion sécurisée
- ✅ Gestion du profil personnel
- ✅ Passation de commandes avec calcul automatique des frais
- ✅ Suivi des commandes en temps réel
- ✅ Historique des commandes
- ✅ Dépôt d'avis sur les commandes terminées
- ✅ Réinitialisation de mot de passe
- ✅ Suppression de compte (RGPD)

### 👷 Utilisateur Employé
- ✅ Dashboard de gestion des commandes
- ✅ Acceptation/refus de commandes avec motifs
- ✅ Mise à jour des statuts de livraison
- ✅ Contact direct avec les clients
- ✅ Gestion des horaires du restaurant
- ✅ Modification du profil personnel

### 👑 Utilisateur Administrateur
- ✅ Toutes les permissions employé
- ✅ Gestion complète des employés (CRUD)
- ✅ Attribution des rôles (employé/administrateur)
- ✅ Statistiques détaillées des commandes
- ✅ Graphiques de performance par période/menu
- ✅ Paramètres système globaux

### 🔧 Fonctionnalités Techniques
- ✅ Architecture SPA (Single Page Application)
- ✅ API REST complète avec JWT
- ✅ Interface responsive (desktop/mobile/tablette)
- ✅ Système d'emails automatique
- ✅ Validation côté client et serveur
- ✅ Protection CSRF et XSS
- ✅ Gestion d'erreurs complète

---

## 🛠️ Technologies Utilisées

### Frontend
- **Framework** : JavaScript ES6+ (Vanilla)
- **Build Tool** : Vite 5.0
- **UI Framework** : Bootstrap 5.3
- **Icons** : Bootstrap Icons 1.13
- **Styling** : SCSS/SASS
- **Routing** : JavaScript SPA Router

### Backend
- **Language** : PHP 8.1+
- **Architecture** : API REST
- **Authentification** : JWT (JSON Web Tokens)
- **Email** : PHPMailer (local) / Resend API (production)
- **Security** : bcrypt (password hashing)

### Base de Données
- **SGBD** : MySQL 8.0+
- **Structure** : 8 tables relationnelles
- **Migration** : Scripts SQL manuels

### DevOps & Outils
- **Versionning** : Git
- **IDE** : Windsurf
- **API Testing** : [à définir]
- **Database Management** : phpMyAdmin 
- **Deployment** : WinSCP - SFTP (production)

### Environnements
- **Développement** : Local (XAMPP Control Panel)
- **Production** : Hébergement mutualisé (OVH)
- **Domaine** : vite-gourmand.maxime-brouazin.fr

---

## 📁 Structure du Projet

```
vite-gourmand/
├── 📁 backend/                          # API PHP
│   ├── 📁 api/                          # Endpoints REST
│   │   ├── 📁 auth/                     # Authentification
│   │   ├── 📁 commands/                 # Gestion commandes
│   │   ├── 📁 menus/                    # Catalogue menus
│   │   ├── 📁 user/                     # Gestion utilisateurs
│   │   └── 📁 admin/                    # Administration
│   ├── 📁 classes/                      # Classes métier
│   ├── 📁 config/                       # Configuration
│   ├── 📁 migrations/                   # Scripts BDD
│   └── 📁 tests/                        # Tests unitaires
│
├── 📁 frontend/                         # Application cliente
│   ├── 📁 pages/                        # Pages HTML
│   ├── 📁 headers/                      # Headers dynamiques
│   ├── 📁 js/                           # Scripts JavaScript
│   ├── 📁 scss/                         # Styles SCSS
│   ├── 📁 public/                       # Assets statiques
│   ├── 📁 Router/                       # Routage SPA
│   ├── index.html                       # Point d'entrée
│   ├── vite.config.js                   # Configuration Vite
│   └── package.json                     # Dépendances npm
│
├── 📄 .htaccess                         # Configuration Apache
├── 📄 composer.json                     # Dépendances PHP
├── 📄 vite_gourmand.sql                 # Dump base de données
├── 📄 AUDIT-COMPLET.md                  # Audit du projet
└── 📄 README.md                         # Documentation (ce fichier)
```

---

## 🚀 Installation & Configuration

### Prérequis Système

#### Pour l'environnement de développement :
- **XAMPP Control Panel v3.3.0** : Fournit Apache (backend) et MySQL (base de données)
- **PHP** : Version 8.1 ou supérieure
- **Node.js** : Version 18+ (pour Vite)
- **Composer** : Pour les dépendances PHP
- **PHPMailer** : Pour les emails en développement local
- **MailHog** : Pour tester les emails en local (port 8025)
- **Git** : Pour le versionning

#### Pour l'environnement de production :
- **Hébergement mutualisé** avec PHP 8.1+ et MySQL 8.0+
- **Accès FTP/SFTP** pour le déploiement
- **Domaine configuré** : vite-gourmand.maxime-brouazin.fr

---

### 🔧 Environnement de Développement (Local)

#### 1. Clonage du Repository
```bash
# Clonez le repository
git clone https://github.com/Nizauorb/Vite-Gourmand.git
cd Vite-Gourmand
```

#### 2. Configuration de la Base de Données
```bash
# Créez une base de données MySQL nommée 'vite_gourmand'
# Importez le fichier SQL
mysql -u root -p vite_gourmand < vite_gourmand.sql
# OU via phpMyAdmin : importez vite_gourmand.sql
```

#### 3. Configuration Backend PHP
```bash
# Installez les dépendances PHP
cd backend
composer install

# Copiez et configurez le fichier de configuration
cp config/config.php.example config/config.php

# Modifiez config/config.php avec vos paramètres locaux :
# - URL de base : http://localhost/Vite-Gourmand
# - Identifiants base de données
# - Configuration PHPMailer pour les emails (MailHog en local)
```

#### 4. Configuration Frontend
```bash
# Depuis la racine du projet
cd frontend

# Installez les dépendances npm
npm install

# Lancez le serveur de développement
npm run dev
```

#### 5. Configuration Serveur Local
```apache
# Assurez-vous que votre serveur local (XAMPP/WAMP)
# pointe vers le dossier racine du projet

# Exemple configuration Apache :
<VirtualHost *:80>
    ServerName vite-gourmand.local
    DocumentRoot "C:/path/to/Vite-Gourmand"

    <Directory "C:/path/to/Vite-Gourmand">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### 6. Variables d'Environnement Local
```php
// backend/config/config.php
return [
    'environment' => 'development',
    'base_url' => 'http://localhost/Vite-Gourmand',
    'database' => [
        'host' => 'localhost',
        'name' => 'vite_gourmand',
        'user' => 'root',
        'password' => ''
    ],
    'mail' => [
        'resend_api_key' => 'votre-cle-api-resend',
        'from_email' => 'noreply@vite-gourmand.local'
    ],
    'jwt' => [
        'secret' => 'votre-cle-secrete-jwt-dev'
    ]
];
```

#### 7. Accès à l'Application Locale
- **Frontend** : http://localhost:3000 (Vite dev server - `npm run dev`)
- **Backend API** : http://localhost/Vite-Gourmand/backend/api/
- **Base de données** : http://localhost/phpmyadmin
- **Emails de test** : http://localhost:8025 (MailHog)

---

### 🌐 Environnement de Production

#### Prérequis Production
- **Hébergement** : OVH, Azure, ou équivalent
- **Domaine** : vite-gourmand.maxime-brouazin.fr
- **PHP** : 8.1+ avec extensions nécessaires
- **MySQL** : 8.0+ avec phpMyAdmin
- **SSL** : Certificat Let's Encrypt recommandé

#### 1. Déploiement Base de Données
```bash
# Via phpMyAdmin de votre hébergeur :
# 1. Créez une base de données 'vite_gourmand_prod'
# 2. Importez le fichier vite_gourmand.sql
# 3. Vérifiez que toutes les tables sont créées
```

#### 2. Configuration Production Backend
```php
// backend/config/config.php
return [
    'environment' => 'production',
    'base_url' => 'https://vite-gourmand.maxime-brouazin.fr',
    'database' => [
        'host' => 'sql.prz.jeuweb.org', // À adapter selon votre hébergeur
        'name' => 'vite_gourmand_prod',
        'user' => 'votre-utilisateur-bdd',
        'password' => 'votre-mot-de-passe-bdd'
    ],
    'mail' => [
        'resend_api_key' => 'votre-cle-api-resend-production',
        'from_email' => 'noreply@vite-gourmand.maxime-brouazin.fr'
    ],
    'jwt' => [
        'secret' => 'votre-cle-secrete-jwt-production-tres-longue-et-complexe'
    ]
];
```

#### 3. Build et Déploiement Frontend
```bash
# Depuis votre machine locale
cd frontend

# Build pour la production
npm run build

# Les fichiers optimisés sont dans le dossier 'dist'
```

#### 4. Déploiement FTP/SFTP
```
# Structure finale sur le serveur :
/public_html/ (ou /www/)
├── 📁 backend/          # Copiez tout le dossier backend
├── 📁 frontend/dist/    # Copiez le contenu de dist/ à la racine
├── 📄 .htaccess         # Configuration Apache
├── 📄 vite_gourmand.sql # Pour référence
└── 📄 README.md         # Documentation
```

#### 5. Configuration Apache (.htaccess)
```apache
# Configuration Apache pour Vite&Gourmand
# Gère le routing SPA + accès API backend

CGIPassAuth On

RewriteEngine On

# Passer l'header Authorization
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:X-Authorization}]

# API Backend - Priorité haute (ne pas réécrire les requêtes backend)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^backend/(.*)$ backend/$1 [L]

# Frontend SPA - Rediriger vers index.html pour les routes non existantes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.html [L,QSA]

# Headers de sécurité
<IfModule mod_headers.c>
    # CORS pour développement (à ajuster en production)
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"

    # Sécurité
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set Referrer-Policy strict-origin-when-cross-origin
</IfModule>

# Gestion des erreurs
ErrorDocument 404 /404.html
ErrorDocument 500 /500.html

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache statique
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/x-shockwave-flash "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresDefault "access plus 2 days"
</IfModule>

```

#### 6. Configuration DNS
- **Domaine principal** : vite-gourmand.maxime-brouazin.fr
- **Sous-domaine API** : api.vite-gourmand.maxime-brouazin.fr (optionnel)
- **Redirections** : www.vite-gourmand.maxime-brouazin.fr → vite-gourmand.maxime-brouazin.fr

#### 7. Tests Post-Déploiement
- ✅ Page d'accueil accessible
- ✅ Inscription/connexion fonctionnelles
- ✅ API endpoints répondent
- ✅ Emails sont envoyés
- ✅ Base de données connectée
- ✅ Certificat SSL valide

---

## 🎮 Utilisation

### Comptes de Test

#### Administrateur (José Martin)
- **Email** : jose.martin@vite-gourmand.fr
- **Mot de passe** : Admin1234!
- **Permissions** : Toutes les fonctionnalités

#### Employé (Antoine Dupont)
- **Email** : antoine.dupont@vite-gourmand.fr
- **Mot de passe** : Employe123!
- **Permissions** : Gestion commandes, profils

#### Client de Test (Maxime Brouazin)
- **Email** : maxime.brouazin@vite-gourmand.fr
- **Mot de passe** : Client123!
- **Permissions** : Commandes, profil, historique

### Parcours Utilisateur Typique

1. **Découverte** : Visite de la page d'accueil
2. **Inscription** : Création de compte client
3. **Navigation** : Consultation du catalogue de menus
4. **Filtrage** : Utilisation des filtres avancés
5. **Commande** : Sélection d'un menu et passage commande
6. **Suivi** : Consultation du statut via le dashboard
7. **Avis** : Dépôt d'avis après livraison

### Fonctionnalités Clés

#### Système de Filtrage
- **Prix** : Fourchette personnalisable (min/max)
- **Personnes** : Nombre minimum requis
- **Régime** : Classique, Végétarien, Vegan, Halal
- **Thème** : Classique, Noël, Pâques, Événements
- **Allergènes** : Exclusion par ingrédient

#### Gestion des Commandes
- **Statuts** : En attente → Acceptée → En préparation → Livrée
- **Notifications** : Emails automatiques à chaque changement
- **Annulation** : Conditions selon le statut
- **Modification** : Possible avant préparation

---

## 📚 API Documentation

### Architecture REST
- **Base URL** : `/backend/api/`
- **Authentification** : JWT dans header `Authorization: Bearer <token>`
- **Format** : JSON pour requests/responses
- **Status Codes** : Standards HTTP (200, 201, 400, 401, 403, 404, 500)

### Endpoints Principaux

#### 🔐 Authentification
```
POST   /auth/register          # Inscription
POST   /auth/login             # Connexion
POST   /auth/forgot-password   # Mot de passe oublié
POST   /auth/reset-password    # Réinitialisation
GET    /auth/me               # Profil utilisateur
PUT    /auth/me               # Mise à jour profil
DELETE /auth/me               # Suppression compte
```

#### 🍽️ Menus
```
GET    /menus                  # Liste des menus
GET    /menus/{id}            # Détails d'un menu
GET    /menus/categories      # Catégories disponibles
```

#### 🛒 Commandes
```
GET    /commands               # Liste des commandes utilisateur
POST   /commands               # Créer une commande
GET    /commands/{id}         # Détails d'une commande
PUT    /commands/{id}/status  # Mise à jour statut (employé/admin)
DELETE /commands/{id}         # Annulation (conditions)
```

#### 👥 Administration
```
GET    /admin/users            # Liste des utilisateurs
PUT    /admin/users/{id}/role # Changement de rôle
GET    /admin/stats           # Statistiques globales
GET    /admin/commands        # Toutes les commandes
```

### Exemple d'Utilisation API

```javascript
// Connexion
const loginResponse = await fetch('/backend/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'client@vite-gourmand.fr',
    password: 'Client123!'
  })
});

const { token } = await loginResponse.json();

// Utilisation du token
const menusResponse = await fetch('/backend/api/menus', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

---

## 🚀 Déploiement

### Pipeline de Déploiement

#### 1. Préparation
```bash
# Build frontend pour production
cd frontend
npm run build

# Test de l'application en local
npm run preview
```

#### 2. Déploiement Base de Données
- Création de la base en production
- Import du dump SQL
- Vérification des données de test

#### 3. Déploiement Code
```bash
# Via FTP/SFTP :
# - Upload backend/ vers /backend/
# - Upload frontend/dist/* vers /
# - Upload .htaccess vers /
```

#### 4. Configuration Post-Déploiement
- Modification des URLs dans la config
- Test des fonctionnalités critiques
- Configuration des emails
- Mise en place du SSL

#### 5. Monitoring
- Tests de charge légers
- Vérification des logs
- Tests fonctionnels complets

### Variables d'Environnement Production
```php
// Clés à sécuriser absolument
'jwt_secret' => 'clé-très-longue-et-complexe-minimum-256-bits'
'mail_api_key' => 'clé-api-resend-production'
'db_password' => 'mot-de-passe-complexe'
```

---

## 🧪 Tests

### Tests Disponibles
```bash
# Tests backend (PHP)
cd backend
php vendor/bin/phpunit tests/

# Tests frontend (manuels recommandés)
# - Tests de navigation
# - Tests de formulaires
# - Tests responsives
# - Tests d'accessibilité
```

### Jeux de Tests Recommandés
1. **Tests d'Inscription/Connexion**
2. **Tests de Commande Complete**
3. **Tests de Filtrage**
4. **Tests d'Administration**
5. **Tests Mobile/Desktop**

---

## 🤝 Contribuer

### Processus de Contribution
1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -m 'Fix: Description claire'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrir une Pull Request

### Standards de Code
- **PHP** : PSR-12, commentaires en français
- **JavaScript** : ES6+, commentaires en français
- **SQL** : Nommage en anglais, commentaires
- **HTML** : Accessibilité RGAA niveau A
- **CSS** : BEM methodology

### Branches
- `main` : Code de production
- `develop` : Développement actif
- `feature/*` : Nouvelles fonctionnalités
- `fix/*` : Corrections de bugs
- `hotfix/*` : Corrections urgentes

---

## 👨‍💻 Auteur

**Maxime Brouazin**
- **Formation** : Développeur Web & Web Mobile
- **ECF** : Évaluation des Compétences en Fin de Formation
- **Portfolio** : maxime-brouazin.fr
- **LinkedIn** : [À définir]
- **GitHub** : [https://github.com/Nizauorb](https://github.com/Nizauorb)

### Compétences Démontrées
- ✅ Architecture web complète (Frontend + Backend)
- ✅ Développement full-stack (JavaScript + PHP)
- ✅ Gestion base de données relationnelle
- ✅ API REST sécurisée
- ✅ Interface utilisateur responsive
- ✅ Sécurité web (authentification, protection)
- ✅ Déploiement et mise en production
- ✅ Gestion de projet et documentation

---

## 📄 Licence

Ce projet est sous licence **ISC**.

```
ISC License

Copyright (c) 2026, Maxime Brouazin

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

---

## 🎯 État du Projet

### ✅ Fonctionnalités Implémentées
- [x] Architecture complète Frontend/Backend
- [x] Système d'authentification JWT
- [x] Catalogue de menus avec filtrage avancé
- [x] Gestion complète des commandes
- [x] Dashboards par rôle utilisateur
- [x] Système d'emails automatique
- [x] Interface responsive mobile/desktop
- [x] Pages légales conformes
- [x] Sécurité de base (CSRF, XSS, validation)
- [x] Base de données relationnelle optimisée

### 🚧 Améliorations Futures
- [ ] Tests automatisés complets
- [ ] Cache API et optimisation performances
- [ ] Système de notifications temps réel
- [ ] Intégration paiement (Stripe)
- [ ] Upload d'images pour menus personnalisés
- [ ] Logs de sécurité détaillés
- [ ] Conformité RGAA complète
- [ ] Programme de fidélité
- [ ] Application mobile native

### 📊 Métriques Projet
- **Lignes de code** : ~15,000+ (Frontend + Backend)
- **Fichiers** : 80+ fichiers organisés
- **Base de données** : 8 tables, données de test complètes
- **API Endpoints** : 25+ routes REST
- **Temps de développement** : ~8 semaines
- **Technologies maîtrisées** : 8+ technologies

---

*Ce projet constitue la validation des compétences acquises durant la formation Développeur Web & Web Mobile. Il démontre la capacité à concevoir, développer et déployer une application web complète de A à Z.*

🎓 **Résultat ECF - à Venir !** 🚀