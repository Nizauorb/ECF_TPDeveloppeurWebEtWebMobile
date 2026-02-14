<?php
// backend/api/admin/list-employes.php
// Liste tous les employés (réservé administrateur)
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
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

$adminId = (int) $jwtPayload['user_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'utilisateur est administrateur
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role = 'administrateur'");
    $stmtRole->execute([$adminId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle administrateur requis']);
        exit();
    }

    // Récupérer tous les employés
    $stmt = $db->prepare("
        SELECT id, email, first_name, last_name, phone, initial_password, created_at
        FROM users
        WHERE role = 'employe'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $employes = $stmt->fetchAll();

    // Convertir les types
    foreach ($employes as &$emp) {
        $emp['id'] = (int) $emp['id'];
    }

    echo json_encode([
        'success' => true,
        'data' => $employes,
        'count' => count($employes)
    ]);

} catch (PDOException $e) {
    error_log("Erreur list-employes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des employés'
    ]);
}
