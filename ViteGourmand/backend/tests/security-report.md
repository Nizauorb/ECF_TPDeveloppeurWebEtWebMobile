# Rapport de Tests de Sécurité — ViteGourmand

**Date :** 2026-02-06 15:40:01  
**Serveur :** http://localhost:3000  
**PHP :** 8.4.13  

---

## Résumé

| Résultat | Nombre |
|----------|--------|
| ✅ PASS  | 27 |
| ❌ FAIL  | 1 |
| ⚠️ WARN  | 2 |
| **Total** | **30** |

**Score global : 90%**

---

## Security Headers

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| X-Content-Type-Options | ✅ PASS | 403 | Présent: nosniff |
| X-Frame-Options | ✅ PASS | 403 | Présent: DENY |
| X-XSS-Protection | ✅ PASS | 403 | Présent: 1; mode=block |
| Strict-Transport-Security | ❌ FAIL | 403 | Header absent |
| Content-Security-Policy | ✅ PASS | 403 | Présent: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests |
| Referrer-Policy | ✅ PASS | 403 | Présent: strict-origin-when-cross-origin |
| CORS Origin | ✅ PASS | 403 | Origine restreinte: *, http://localhost:3000 |

## CSRF

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| POST sans CSRF token | ✅ PASS | 403 | Rejeté avec 403 comme attendu |
| Génération du token | ✅ PASS | 200 | Token reçu: 0679aaa81e4d... |
| POST avec CSRF token valide | ✅ PASS | 400 | Accepté (HTTP 400) |
| POST avec CSRF token invalide | ✅ PASS | 403 | Rejeté avec 403 comme attendu |

## Rate Limiting

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| Login — limite atteinte à la requête 11 | ✅ PASS | 429 | 429 reçu après 11 requêtes (limite configurée: 10) |
| Headers X-RateLimit | ✅ PASS | 429 | Limit: 10, Remaining: 0 |

## Input Validation

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| Mauvais Content-Type | ✅ PASS | 415 | Rejeté avec 415 comme attendu |
| Email invalide rejeté | ✅ PASS | 400 | HTTP 400: L'adresse email est invalide |
| Nom trop court rejeté | ✅ PASS | 400 | HTTP 400: L'email contient des éléments suspects |

## CORS

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| Preflight OPTIONS | ⚠️ WARN | 204 | Code: 204 |
| Origine malveillante bloquée | ✅ PASS | 403 | Origin evil-site.com non reflété (valeur: *) |

## Masquage erreurs

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| login.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| register.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| reset-password.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| update-profile.php | ✅ PASS | 405 | Aucune fuite d'information détectée |
| confirm-email-change.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| confirm-delete-account.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| request-delete-account.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| request-password-reset.php | ✅ PASS | 400 | Aucune fuite d'information détectée |
| user-commands.php | ✅ PASS | 405 | Aucune fuite d'information détectée |

## Méthodes HTTP

| Test | Résultat | HTTP | Détail |
|------|----------|------|--------|
| login.php — GET bloqué | ✅ PASS | 405 | Rejeté avec 405 Method Not Allowed |
| register.php — GET bloqué | ✅ PASS | 405 | Rejeté avec 405 Method Not Allowed |
| forgot-password.php — GET bloqué | ⚠️ WARN | 415 | Attendu 405, reçu 415 |

---

## Configuration de sécurité active

```php
// Extrait de backend/config/security.php
'rate_limits' => [
    'contact' => 3 req / 300s,
    'forgot_password' => 5 req / 900s,
    'login' => 10 req / 900s,
    'register' => 5 req / 3600s,
    'reset_password' => 5 req / 900s,
    'profile_update' => 10 req / 300s,
    'email_change' => 5 req / 900s,
    'email_change_confirm' => 5 req / 300s,
    'delete_account' => 3 req / 900s,
    'delete_account_confirm' => 5 req / 300s,
    'password_reset_request' => 3 req / 900s,
]
'csrf.token_lifetime' => 3600s
'input.max_input_size' => 1048576 bytes
```

---

*Rapport généré automatiquement par `backend/tests/security-test.php`*
