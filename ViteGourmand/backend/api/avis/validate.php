<?php
// backend/api/avis/validate.php
// Valider ou refuser un avis (réservé employé/administrateur)
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

    if (!$data || !isset($data['id']) || !isset($data['valide'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs requis : id, valide (1 = valider, 2 = refuser)']);
        exit();
    }

    $avisId = (int) $data['id'];
    $valide = (int) $data['valide'];

    if (!in_array($valide, [1, 2])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valeur invalide pour "valide" (1 = valider, 2 = refuser)']);
        exit();
    }

    // Vérifier que l'avis existe
    $stmtCheck = $db->prepare("SELECT id, valide FROM avis WHERE id = ?");
    $stmtCheck->execute([$avisId]);
    $avis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$avis) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Avis non trouvé']);
        exit();
    }

    // Mettre à jour le statut
    $stmtUpdate = $db->prepare("UPDATE avis SET valide = ?, validated_by = ?, validated_at = NOW() WHERE id = ?");
    $stmtUpdate->execute([$valide, $operatorId, $avisId]);

    $statusText = $valide === 1 ? 'validé' : 'refusé';

    echo json_encode([
        'success' => true,
        'message' => "Avis #{$avisId} {$statusText} avec succès"
    ]);

} catch (PDOException $e) {
    error_log("Erreur validation avis: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la validation de l\'avis']);
}
