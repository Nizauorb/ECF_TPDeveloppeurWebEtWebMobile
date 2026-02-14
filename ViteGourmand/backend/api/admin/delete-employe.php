<?php
// backend/api/admin/delete-employe.php
// Supprimer un compte employé (réservé administrateur)
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/JWTHelper.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Protection CSRF
CSRFProtection::requireValidation();

// Validation JWT
$jwtPayload = JWTHelper::getFromRequest();
if (!$jwtPayload || !isset($jwtPayload['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise']);
    exit();
}

$adminId = (int) $jwtPayload['user_id'];

// Récupérer l'ID de l'employé à supprimer
$employeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($employeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID employé invalide']);
    exit();
}

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

    // Empêcher la suppression de soi-même
    if ($employeId === $adminId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte']);
        exit();
    }

    // Vérifier que l'utilisateur cible est bien un employé
    $stmtCheck = $db->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id = ?");
    $stmtCheck->execute([$employeId]);
    $employe = $stmtCheck->fetch();

    if (!$employe) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Employé introuvable']);
        exit();
    }

    if ($employe['role'] !== 'employe') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cet utilisateur n\'est pas un employé']);
        exit();
    }

    // Supprimer l'employé (CASCADE supprimera les entrées liées)
    $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'employe'");
    $stmtDelete->execute([$employeId]);

    if ($stmtDelete->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Compte employé supprimé avec succès',
        'data' => [
            'deletedEmploye' => [
                'id' => (int) $employe['id'],
                'firstName' => $employe['first_name'],
                'lastName' => $employe['last_name'],
                'email' => $employe['email']
            ]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur delete-employe.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la suppression du compte employé'
    ]);
}
