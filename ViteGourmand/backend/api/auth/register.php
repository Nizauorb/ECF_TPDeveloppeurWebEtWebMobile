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

// Validation des champs requis
$requiredFields = ['lastName', 'firstName', 'email', 'password', 'confirmPassword', 'phone', 'address'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Champs manquants : ' . implode(', ', $missingFields)
    ]);
    exit();
}

// Validation de l'email
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Format d\'email invalide'
    ]);
    exit();
}

// Vérification de la correspondance des mots de passe
if ($data['password'] !== $data['confirmPassword']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Les mots de passe ne correspondent pas'
    ]);
    exit();
}

// Validation de la force du mot de passe
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{10,}$/', $data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
    ]);
    exit();
}

// Validation du numéro de téléphone
if (!preg_match('/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/', $data['phone'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Format de numéro de téléphone invalide (format français attendu)'
    ]);
    exit();
}

try {
    // Connexion à la base de données
    $db = Database::getInstance()->getConnection();

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode([
            'success' => false,
            'message' => 'Cette adresse email est déjà utilisée'
        ]);
        exit();
    }

    // Hachage du mot de passe
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insertion du nouvel utilisateur
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, phone, address)
        VALUES (:email, :password_hash, :first_name, :last_name, :phone, :address)
    ");

    $stmt->execute([
        ':email' => $data['email'],
        ':password_hash' => $passwordHash,
        ':first_name' => $data['firstName'],
        ':last_name' => $data['lastName'],
        ':phone' => $data['phone'],
        ':address' => $data['address']
    ]);

    $userId = $db->lastInsertId();

    // Réponse de succès
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Inscription réussie',
        'data' => [
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'role' => 'utilisateur'
            ]
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'inscription',
        'error' => $e->getMessage()
    ]);
}