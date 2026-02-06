<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
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

// Rate Limiting
RateLimiter::setRateLimitHeaders('reset_password');

if (!RateLimiter::checkLimit('reset_password')) {
    $waitTime = RateLimiter::getWaitTime('reset_password');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
$newPassword = $data['password'] ?? '';

if (empty($token) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token et nouveau mot de passe requis']);
    exit();
}

if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Vérifier le token
    $stmt = $db->prepare("
        SELECT prt.*, u.id as user_id 
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? 
        AND prt.expires_at > NOW() 
        AND prt.used = 0
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lien invalide ou expiré']);
        exit();
    }

    // Mettre à jour le mot de passe avec le même algorithme de hachage que l'inscription
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe dans la base de données
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $updateSuccess = $stmt->execute([$hashedPassword, $tokenData['user_id']]);
    
    // Vérifier que la mise à jour a réussi
    if (!$updateSuccess || $stmt->rowCount() === 0) {
        throw new Exception("Échec de la mise à jour du mot de passe");
    }
    
    // Marquer le token comme utilisé
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    $stmt->execute([$token]);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);

} catch (Exception $e) {
    error_log("Erreur dans reset-password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}