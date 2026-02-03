<?php
// backend/api/csrf/token.php

// Headers CORS et sécurité
header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000'));
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token");

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
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
