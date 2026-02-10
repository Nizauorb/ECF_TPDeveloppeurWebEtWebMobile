<?php
// backend/api/menus/plats/create.php
// Créer un nouveau plat (réservé employé/administrateur)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Validation des champs obligatoires
    if (!isset($data['nom']) || trim($data['nom']) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champ manquant : nom']);
        exit();
    }

    if (!isset($data['type']) || trim($data['type']) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champ manquant : type']);
        exit();
    }

    $allowedTypes = ['entree', 'plat', 'dessert'];
    if (!in_array($data['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Type invalide (entree, plat ou dessert)']);
        exit();
    }

    // Sanitization
    $nom = htmlspecialchars(trim($data['nom']), ENT_QUOTES, 'UTF-8');
    $type = $data['type'];
    $allergenes = isset($data['allergenes']) && is_array($data['allergenes']) ? json_encode($data['allergenes']) : null;

    // Insertion
    $stmt = $db->prepare("INSERT INTO plats (nom, type, allergenes) VALUES (?, ?, ?)");
    $stmt->execute([$nom, $type, $allergenes]);

    $platId = (int) $db->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Plat créé avec succès',
        'data' => ['id' => $platId, 'nom' => $nom, 'type' => $type]
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/plats/create.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création du plat'
    ]);
}
