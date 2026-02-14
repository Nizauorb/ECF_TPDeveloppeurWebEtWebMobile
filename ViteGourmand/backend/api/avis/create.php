<?php
// backend/api/avis/create.php
// Créer un avis pour une commande terminée (utilisateur connecté)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$userId = (int) $jwtPayload['user_id'];

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['commande_id']) || !isset($data['note']) || !isset($data['commentaire'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Champs requis : commande_id, note, commentaire']);
    exit();
}

$commandeId = (int) $data['commande_id'];
$note = (int) $data['note'];
$commentaire = trim($data['commentaire']);

// Validation de la note (1-5)
if ($note < 1 || $note > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La note doit être comprise entre 1 et 5']);
    exit();
}

// Validation du commentaire
if (empty($commentaire) || strlen($commentaire) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le commentaire doit contenir au moins 10 caractères']);
    exit();
}

if (strlen($commentaire) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le commentaire ne doit pas dépasser 1000 caractères']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que la commande existe, appartient à l'utilisateur et est terminée
    $stmtCmd = $db->prepare("SELECT id, user_id, statut FROM commandes WHERE id = ?");
    $stmtCmd->execute([$commandeId]);
    $commande = $stmtCmd->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit();
    }

    if ((int) $commande['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cette commande ne vous appartient pas']);
        exit();
    }

    if ($commande['statut'] !== 'terminee') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez laisser un avis que pour une commande terminée']);
        exit();
    }

    // Vérifier qu'un avis n'existe pas déjà pour cette commande
    $stmtCheck = $db->prepare("SELECT id FROM avis WHERE commande_id = ?");
    $stmtCheck->execute([$commandeId]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un avis a déjà été laissé pour cette commande']);
        exit();
    }

    // Créer l'avis
    $stmtInsert = $db->prepare("INSERT INTO avis (user_id, commande_id, note, commentaire) VALUES (?, ?, ?, ?)");
    $stmtInsert->execute([$userId, $commandeId, $note, $commentaire]);

    $avisId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Merci pour votre avis ! Il sera visible après validation par notre équipe.',
        'data' => [
            'id' => (int) $avisId,
            'commande_id' => $commandeId,
            'note' => $note,
            'commentaire' => $commentaire,
            'valide' => 0
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur création avis: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de l\'avis']);
}
