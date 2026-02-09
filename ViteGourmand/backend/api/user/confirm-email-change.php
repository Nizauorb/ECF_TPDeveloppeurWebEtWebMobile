<?php
// backend/api/user/confirm-email-change.php
// Valide le code de vérification et applique le changement d'email
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

// Rate Limiting - Anti-bruteforce code 6 chiffres
RateLimiter::setRateLimitHeaders('email_change_confirm');

if (!RateLimiter::checkLimit('email_change_confirm')) {
    $waitTime = RateLimiter::getWaitTime('email_change_confirm');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['user_id']) || !isset($data['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID utilisateur et code requis']);
    exit();
}

$userId = intval($data['user_id']);
$code = trim($data['code']);

if (strlen($code) !== 6 || !ctype_digit($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le code doit contenir 6 chiffres']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Chercher une demande valide (non utilisée, non expirée)
    $stmt = $db->prepare("
        SELECT id, new_email, verification_code
        FROM email_change_requests
        WHERE user_id = ? AND used = 0 AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune demande de changement d\'email en cours ou le code a expiré.'
        ]);
        exit();
    }

    // Vérifier le code
    if ($request['verification_code'] !== $code) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Code de vérification incorrect.'
        ]);
        exit();
    }

    $newEmail = $request['new_email'];

    // Vérifier une dernière fois que le nouvel email n'est pas pris
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        // Marquer la demande comme utilisée
        $stmt = $db->prepare("UPDATE email_change_requests SET used = 1 WHERE id = ?");
        $stmt->execute([$request['id']]);

        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cet email est désormais utilisé par un autre compte.'
        ]);
        exit();
    }

    // Appliquer le changement d'email
    $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$newEmail, $userId]);

    // Marquer la demande comme utilisée
    $stmt = $db->prepare("UPDATE email_change_requests SET used = 1 WHERE id = ?");
    $stmt->execute([$request['id']]);

    echo json_encode([
        'success' => true,
        'new_email' => $newEmail,
        'message' => 'Adresse email mise à jour avec succès !'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Erreur interne'
    ]);
}
