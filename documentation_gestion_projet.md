# Documentation de la Gestion de Projet – Vite&Gourmand

## 1. Vision Globale du Projet

- **Contexte** : Application web complète (SPA Vite côté front, API PHP/MySQL côté back) livrée dans le cadre de l'ECF Développeur Web & Web Mobile 2025/2026 pour STUDI. Trois rôles principaux : clients, employés, administrateurs.
- **Objectif** : Proposer une plateforme de commande traiteur « production ready » démontrant toutes les compétences évaluées (maquettage, front dynamique, API sécurisée, BDD, déploiement, documentation).
- **Livrables majeurs** : fonctionnalités métiers complètes, audit technique (accessibilité/RGPD), manuel utilisateur, README détaillé, déploiement en production.
---

## 2. Organisation et Pilotage

### 2.1 Périmètre fonctionnel
Le document **Brouillon.md** rassemble les parcours, rôles et règles métier (menus, process commande, accès par rôle, contraintes RGPD/RGAA). Il a servi de cahier des charges racourci pour alimenter le Kanban.

### 2.2 Méthode et routines
- **Cadence** : itérations courtes de type agile/kanban pour livrer par blocs (Authentification → Catalogue → Commandes → Dashboards → Conformité).
- **Outil** : espace **Notion** avec un tableau Kanban structuré en colonnes :
  1. *À faire*
  2. *En cours*
  3. *Terminé*
- **Structure de carte** : description fonctionnelle, références (maquettes/doc), critères d'acceptation, checklist technique (endpoints, validations, tests)

### 2.3 Traçabilité documentaire
- **README** : installation, architecture, comptes de test, procédures de déploiement.
- **AUDIT-COMPLET** : état du produit, checklists RGPD/RGAA, recommandations.
- **Manuel d’utilisation** : scénarios par rôle (visiteur, client, employé, admin).

---

## 3. Découpage en Sous-Tâches (exemples)

| Domaine | Carte Kanban | Détails & livrables |
| --- | --- | --- |
| Authentification | « Flow inscription + emails » | Formulaires conformes RGPD, hash + JWT, emails bienvenue/reset, suppression compte. |
| Catalogue menus | « Catalogue + filtres dynamiques » | Fiches menus, filtres multi-critères, pré-renseignement commande, cohérence avec Brouillon. |
| Process commande | « Processus complet commande » | Étapes front (menu → livraison → récap), API, calcul frais, emails, statuts métier. |
| Dashboards | « Espace employé/admin » | Gestion commandes, menus, horaires, comptes employés, statistiques. |
| Conformité | « RGPD/RGAA pass » | Checklists, mentions légales, suppressions compte, plan d’actions accessibilité. |
| Documentation | « Kit jury » | README, manuel utilisateur, audit, preuves déploiement (captures, URLs). |

Chaque carte progresse dans Notion en respectant les critères définis. Les dépendances (ex : API avant interface) sont mentionnées dans la checklist technique.

---

## 4. Gestion des Risques et Arbitrages

- **Accessibilité (RGAA)** : actions priorisées (labels, focus, contrastes). Score actuel ~65 % (cf. audit).
- **RGPD** : fonctions essentielles livrées (suppression compte, emails transactionnels, mentions légales). Les chantiers restants (export automatique, registre des traitements) sont planifiés dans les recommandations de l’audit.

---

## 5. Synthèse de l’utilisation de Notion (Kanban)

1. **À faire** : objectifs de l’itération (ex. « Dashboard employé », « Checklist RGAA »).
2. **En cours** : tâches actives avec checklist technique (endpoints, composants front, tests) pour garantir la complétude.
3. **Terminé** : tâches validées, chaque carte référence :
   - Commit ou branche correspondante
   - Section du README/audit/manuel mise à jour

Ce fonctionnement permet un suivi transparent du besoin jusqu’au livrable, tout en assurant la cohérence documentaire exigée pour l’ECF.

---

## 6. Conclusion

La combinaison **Brouillon → Kanban Notion → Documentation** a garanti :
- Une vision claire du périmètre et des priorités.
- Une exécution incrémentale contrôlée (tracking par colonnes Kanban).
- Une traçabilité complète grâce aux mises à jour croisées (README, Audit, Manuel).

Résultat : un produit « production ready », documenté, et facilement présentable au jury (installation guidée, comptes tests, audit de conformité, manuel d’utilisation).
