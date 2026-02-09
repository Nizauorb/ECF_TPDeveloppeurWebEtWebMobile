<?php
// backend/api/csrf/token.php

require_once __DIR__ . '/../../classes/SecurityHeaders.php';

// Headers CORS et sécurité
SecurityHeaders::setSecureCORS();
header("Content-Type: application/json; charset=UTF-8");

// Configurer les cookies de session pour la production
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

// Uniquement les requêtes GET sont autorisées
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

try {
    // Inclure la classe CSRF
    require_once __DIR__ . '/../../classes/CSRFProtection.php';
    
    // Générer et retourner le token
    $token = CSRFProtection::generateToken();
    
    // Ajouter le token aux headers aussi
    CSRFProtection::setTokenHeader();
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $token,
        'expires_in' => 3600 // 1 heure en secondes
    ]);
    
} catch (Exception $e) {
    error_log("Erreur génération token CSRF: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur'
    ]);
}
