<?php
// backend/config/security.php
// Configuration centralisée de la sécurité
// Modifiez ce fichier pour ajuster les règles sans toucher au code

return [

    // =========================================================================
    // Rate Limiting — Limites de requêtes par IP
    // 'requests' = nombre max de requêtes autorisées
    // 'window'   = durée de la fenêtre en secondes
    // =========================================================================
    'rate_limits' => [
        'contact'                => ['requests' => 3,  'window' => 300],   // 3 emails / 5 min
        'forgot_password'        => ['requests' => 5,  'window' => 900],   // 5 demandes / 15 min
        'login'                  => ['requests' => 10, 'window' => 900],   // 10 tentatives / 15 min
        'register'               => ['requests' => 5,  'window' => 3600],  // 5 inscriptions / 1h
        'reset_password'         => ['requests' => 5,  'window' => 900],   // 5 resets / 15 min
        'profile_update'         => ['requests' => 10, 'window' => 300],   // 10 modifs / 5 min
        'email_change'           => ['requests' => 5,  'window' => 900],   // 5 tentatives / 15 min
        'email_change_confirm'   => ['requests' => 5,  'window' => 300],   // 5 essais code / 5 min
        'delete_account'         => ['requests' => 3,  'window' => 900],   // 3 demandes / 15 min
        'delete_account_confirm' => ['requests' => 5,  'window' => 300],   // 5 essais code / 5 min
        'password_reset_request' => ['requests' => 3,  'window' => 900],   // 3 demandes / 15 min
    ],

    // Limite par défaut si l'action n'est pas définie ci-dessus
    'rate_limit_default' => ['requests' => 1, 'window' => 60],

    // =========================================================================
    // CSRF Protection
    // =========================================================================
    'csrf' => [
        'token_lifetime' => 3600,  // Durée de vie du token en secondes (1 heure)
    ],

    // =========================================================================
    // Input Validation
    // =========================================================================
    'input' => [
        'max_input_size'   => 1 * 1024 * 1024,  // Taille max des données entrantes (1 Mo)
        'name_min_length'  => 2,
        'name_max_length'  => 100,
        'message_min_length' => 10,
        'message_max_length' => 2000,
        'email_max_length' => 254,
    ],

    // =========================================================================
    // Codes de vérification (email change, suppression compte)
    // =========================================================================
    'verification_codes' => [
        'length'      => 6,           // Nombre de chiffres du code
        'expiration'  => 15,          // Durée de vie en minutes
    ],

    // =========================================================================
    // CORS — Origines autorisées
    // =========================================================================
    'cors' => [
        'allowed_origins' => [
            'http://localhost:3000',
            'https://localhost:3000',
            'http://127.0.0.1:3000',
            'https://127.0.0.1:3000',
            'https://vite-gourmand.maxime-brouazin.fr',
            'http://vite-gourmand.maxime-brouazin.fr',
        ],
    ],

    // =========================================================================
    // Mots de passe
    // =========================================================================
    'password' => [
        'min_length' => 10,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_digit'     => true,
        'require_special'   => true,
    ],

];
