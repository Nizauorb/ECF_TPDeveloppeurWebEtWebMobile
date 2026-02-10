<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Inclure les dépendances
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
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

// Validation de la taille et du type de contenu
if (!InputValidator::validateInputSize()) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Les données envoyées sont trop volumineuses']);
    exit();
}

if (!InputValidator::validateContentType()) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Type de contenu non autorisé']);
    exit();
}

// Protection CSRF
CSRFProtection::requireValidation();

// Rate Limiting - Anti-spam inscription
RateLimiter::setRateLimitHeaders('register');

if (!RateLimiter::checkLimit('register')) {
    $waitTime = RateLimiter::getWaitTime('register');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

// Lire les données JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validation des champs requis
$requiredFields = ['lastName', 'firstName', 'email', 'password', 'confirmPassword', 'phone', 'adresse', 'code_postal', 'ville'];
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

// Validation de l'email avec InputValidator
$emailValidation = InputValidator::validateEmail($data['email']);
if (!$emailValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $emailValidation['error']]);
    exit();
}

// Validation du nom et prénom avec InputValidator
$lastNameValidation = InputValidator::validateName($data['lastName']);
if (!$lastNameValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nom : ' . $lastNameValidation['error']]);
    exit();
}

$firstNameValidation = InputValidator::validateName($data['firstName']);
if (!$firstNameValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Prénom : ' . $firstNameValidation['error']]);
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
    $stmt->execute([$emailValidation['sanitized']]);
    
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
        INSERT INTO users (email, password_hash, first_name, last_name, phone, adresse, code_postal, ville, pays)
        VALUES (:email, :password_hash, :first_name, :last_name, :phone, :adresse, :code_postal, :ville, :pays)
    ");

    $stmt->execute([
        ':email' => $emailValidation['sanitized'],
        ':password_hash' => $passwordHash,
        ':first_name' => $firstNameValidation['sanitized'],
        ':last_name' => $lastNameValidation['sanitized'],
        ':phone' => $data['phone'],
        ':adresse' => trim($data['adresse']),
        ':code_postal' => trim($data['code_postal']),
        ':ville' => trim($data['ville']),
        ':pays' => trim($data['pays'] ?? 'France')
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
        'error' => 'Erreur interne'
    ]);
}