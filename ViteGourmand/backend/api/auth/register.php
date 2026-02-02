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

// Vérifier les données requises
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
        'message' => 'Champs manquants : ' . implode(', ', $missingFields),
        'missing_fields' => $missingFields
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

// Validation du mot de passe (au moins 10 caractères, majuscule, minuscule, chiffre, caractère spécial)
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]).{10,}$/', $data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
    ]);
    exit();
}

// Validation du numéro de téléphone (format français)
if (!preg_match('/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/', $data['phone'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Format de numéro de téléphone invalide (format français attendu)'
    ]);
    exit();
}

// Simulation d'inscription réussie (à remplacer par un vrai enregistrement en base de données)
$userId = rand(1000, 9999);

// Réponse de succès
http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Inscription réussie',
    'data' => [
        'user' => [
            'id' => $userId,
            'lastName' => $data['lastName'],
            'firstName' => $data['firstName'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'role' => 'utilisateur',
            'createdAt' => date('Y-m-d H:i:s')
        ]
    ]
]);