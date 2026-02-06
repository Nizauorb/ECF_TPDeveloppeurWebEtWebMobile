<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Inclure la configuration et la connexion à la base de données
require_once __DIR__ . '/../../classes/Database.php';

// Configuration des headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS
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

// Lire les données JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validation des champs
if (!$data || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email et mot de passe requis'
    ]);
    exit();
}

try {
    // Connexion à la base de données
    $db = Database::getInstance()->getConnection();

    // Récupérer l'utilisateur par email
    $stmt = $db->prepare("
        SELECT id, email, password_hash, first_name, last_name, phone, address, role 
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    // Vérifier si l'utilisateur existe et le mot de passe est correct
    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ]);
        exit();
    }

    // Générer un token JWT (à implémenter)
    $token = bin2hex(random_bytes(32));
    
    // Ici, vous devriez stocker le token dans la base de données
    // avec une date d'expiration

    // Réponse de succès
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'firstName' => $user['first_name'],
                'lastName' => $user['last_name'],
                'phone' => $user['phone'],
                'address' => $user['address'],
                'role' => $user['role']
            ]
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la connexion',
        'error' => $e->getMessage()
    ]);
}