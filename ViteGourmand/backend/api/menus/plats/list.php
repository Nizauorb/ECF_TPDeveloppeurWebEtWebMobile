<?php
// backend/api/menus/plats/list.php
// Liste tous les plats (réservé employé/administrateur)
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    // Filtre optionnel par type
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $allowedTypes = ['entree', 'plat', 'dessert'];

    if ($type && in_array($type, $allowedTypes)) {
        $stmt = $db->prepare("SELECT * FROM plats WHERE type = ? ORDER BY type, nom");
        $stmt->execute([$type]);
    } else {
        $stmt = $db->prepare("SELECT * FROM plats ORDER BY FIELD(type, 'entree', 'plat', 'dessert'), nom");
        $stmt->execute();
    }

    $plats = $stmt->fetchAll();

    // Typage + décoder les allergènes JSON
    foreach ($plats as &$plat) {
        $plat['id'] = (int) $plat['id'];
        $plat['allergenes'] = json_decode($plat['allergenes'], true) ?? [];
    }

    echo json_encode([
        'success' => true,
        'data' => $plats,
        'count' => count($plats)
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/plats/list.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des plats'
    ]);
}
