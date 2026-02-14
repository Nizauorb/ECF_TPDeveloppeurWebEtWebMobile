<?php
// backend/api/commands/cancel.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Protection CSRF
CSRFProtection::requireValidation();

// Rate Limiting
RateLimiter::setRateLimitHeaders('create_order');

if (!RateLimiter::checkLimit('create_order')) {
    $waitTime = RateLimiter::getWaitTime('create_order');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validation des données
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;

// Validation JWT : extraire le user_id du token si disponible
$jwtPayload = JWTHelper::getFromRequest();
$userId = ($jwtPayload && isset($jwtPayload['user_id'])) ? (int) $jwtPayload['user_id'] : (isset($data['user_id']) ? (int) $data['user_id'] : 0);

if ($orderId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que la commande existe et appartient à l'utilisateur
    $stmt = $db->prepare("SELECT id, statut FROM commandes WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $commande = $stmt->fetch();

    if (!$commande) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit();
    }

    // Vérifier que la commande peut être annulée (uniquement si en_attente)
    if ($commande['statut'] !== 'en_attente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cette commande ne peut plus être annulée (statut : ' . $commande['statut'] . ')']);
        exit();
    }

    // Annuler la commande
    $stmtUpdate = $db->prepare("UPDATE commandes SET statut = 'annulee', updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmtUpdate->execute([$orderId, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Commande annulée avec succès'
    ]);

} catch (PDOException $e) {
    error_log("Erreur annulation commande: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'annulation de la commande']);
}
