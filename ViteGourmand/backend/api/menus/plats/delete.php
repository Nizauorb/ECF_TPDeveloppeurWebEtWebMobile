<?php
// backend/api/menus/plats/delete.php
// Supprimer un plat (réservé employé/administrateur)
// Suppression définitive (hard delete) — les associations menu_plats sont supprimées en cascade
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../../classes/JWTHelper.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Validation JWT
$jwtPayload = JWTHelper::getFromRequest();
if (!$jwtPayload || !isset($jwtPayload['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise']);
    exit();
}

$operatorId = (int) $jwtPayload['user_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier le rôle
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role IN ('employe', 'administrateur')");
    $stmtRole->execute([$operatorId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle insuffisant']);
        exit();
    }

    // Récupérer l'ID du plat
    $platId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($platId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du plat requis']);
        exit();
    }

    // Vérifier que le plat existe
    $stmtCheck = $db->prepare("SELECT id, nom FROM plats WHERE id = ?");
    $stmtCheck->execute([$platId]);
    $plat = $stmtCheck->fetch();

    if (!$plat) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Plat introuvable']);
        exit();
    }

    // Suppression définitive (les associations menu_plats sont supprimées via ON DELETE CASCADE)
    $stmtDelete = $db->prepare("DELETE FROM plats WHERE id = ?");
    $stmtDelete->execute([$platId]);

    echo json_encode([
        'success' => true,
        'message' => "Plat \"{$plat['nom']}\" supprimé avec succès"
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/plats/delete.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression du plat'
    ]);
}
