<?php
// Configuration des headers pour CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS (pre-flight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// Lire les données JSON envoyées
$data = json_decode(file_get_contents('php://input'), true);

// Test simple : vérifier si on reçoit les données
if (!$data || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Données manquantes',
        'debug' => [
            'received_data' => $data,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'non défini'
        ]
    ]);
    exit();
}

// Simulation de connexion (sans base de données)
if ($data['email'] === 'test@example.com' && $data['password'] === 'password123') {
    // Succès simulé
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie (mode test)',
        'data' => [
            'user' => [
                'id' => 1,
                'email' => $data['email'],
                'nom' => 'Test',
                'prenom' => 'User',
                'role' => 'utilisateur',
                'telephone' => '0612345678'
            ],
            'token' => 'test_token_' . time()
        ]
    ]);
} else {
    // Échec simulé
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Email ou mot de passe incorrect (mode test)',
        'debug' => [
            'email_test' => $data['email'],
            'password_received' => '***',
            'expected_email' => 'test@example.com',
            'expected_password' => 'password123'
        ]
    ]);
}
?>