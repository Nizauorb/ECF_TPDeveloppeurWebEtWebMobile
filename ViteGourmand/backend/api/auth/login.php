<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Inclure les dépendances
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';
require_once __DIR__ . '/../../classes/JWTHelper.php';

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

// Rate Limiting - Anti-bruteforce
RateLimiter::setRateLimitHeaders('login');

if (!RateLimiter::checkLimit('login')) {
    $waitTime = RateLimiter::getWaitTime('login');
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

// Validation des champs avec InputValidator
if (!$data || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email et mot de passe requis'
    ]);
    exit();
}

$emailValidation = InputValidator::validateEmail($data['email']);
if (!$emailValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $emailValidation['error']]);
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
    $stmt->execute([$emailValidation['sanitized']]);
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

    // Générer un token JWT signé
    $token = JWTHelper::generate([
        'user_id' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role']
    ]);

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
        'error' => 'Erreur interne'
    ]);
}