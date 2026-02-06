<?php
// backend/api/user/confirm-delete-account.php
// Étape 2 : valide le code et supprime définitivement le compte
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
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
        SELECT id, verification_code
        FROM account_deletion_requests
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
            'message' => 'Aucune demande de suppression en cours ou le code a expiré.'
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

    // Marquer la demande comme utilisée
    $stmt = $db->prepare("UPDATE account_deletion_requests SET used = 1 WHERE id = ?");
    $stmt->execute([$request['id']]);

    // Supprimer les données liées puis le compte
    // Les FK ON DELETE CASCADE s'occupent des tables liées (commandes, tokens, etc.)
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Votre compte a été supprimé définitivement.'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé.'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}
