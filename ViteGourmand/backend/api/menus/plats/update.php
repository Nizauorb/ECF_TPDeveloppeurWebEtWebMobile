<?php
// backend/api/menus/plats/update.php
// Modifier un plat existant (réservé employé/administrateur)
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

    $data = json_decode(file_get_contents('php://input'), true);

    // ID du plat à modifier
    $platId = isset($data['id']) ? (int) $data['id'] : 0;
    if ($platId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du plat requis']);
        exit();
    }

    // Vérifier que le plat existe
    $stmtCheck = $db->prepare("SELECT id FROM plats WHERE id = ?");
    $stmtCheck->execute([$platId]);
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Plat introuvable']);
        exit();
    }

    // Construction dynamique de la requête UPDATE
    $fields = [];
    $params = [];

    if (isset($data['nom']) && trim($data['nom']) !== '') {
        $fields[] = "nom = ?";
        $params[] = htmlspecialchars(trim($data['nom']), ENT_QUOTES, 'UTF-8');
    }

    if (isset($data['type'])) {
        $allowedTypes = ['entree', 'plat', 'dessert'];
        if (!in_array($data['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type invalide (entree, plat ou dessert)']);
            exit();
        }
        $fields[] = "type = ?";
        $params[] = $data['type'];
    }

    if (isset($data['allergenes'])) {
        $fields[] = "allergenes = ?";
        $params[] = is_array($data['allergenes']) ? json_encode($data['allergenes']) : null;
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucun champ à mettre à jour']);
        exit();
    }

    $params[] = $platId;
    $sql = "UPDATE plats SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Plat mis à jour avec succès'
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/plats/update.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du plat'
    ]);
}
