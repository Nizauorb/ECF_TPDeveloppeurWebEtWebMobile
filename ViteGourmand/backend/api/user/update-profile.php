<?php
// backend/api/user/update-profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../classes/Database.php';

// Configuration des headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// Lire les données JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validation des champs requis
if (!$data || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID utilisateur requis'
    ]);
    exit();
}

$userId = intval($data['user_id']);
$firstName = trim($data['first_name'] ?? '');
$lastName = trim($data['last_name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$address = trim($data['address'] ?? '');

// Validation basique
if (empty($firstName) || empty($lastName) || empty($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nom, prénom et email sont obligatoires'
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Format d\'email invalide'
    ]);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cet email est déjà utilisé par un autre compte'
        ]);
        exit();
    }

    // Mettre à jour le profil
    $stmt = $db->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([$firstName, $lastName, $email, $phone, $address, $userId]);

    if ($stmt->rowCount() >= 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du profil',
        'error' => $e->getMessage()
    ]);
}
