<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Import des classes nécessaires
use DateTime;

// Définir les en-têtes CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inclure les dépendances
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/EmailService.php';

try {
    // Récupérer les données JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Vérifier si l'email est présent
    if (empty($data['email'])) {
        throw new Exception('Email est requis');
    }
    
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Email invalide');
    }

    // Vérifier si l'utilisateur existe
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    } catch (Exception $dbError) {
        error_log("Erreur base de données dans forgot-password: " . $dbError->getMessage());
        
        // Pour des raisons de sécurité, on simule une réponse normale même si la BDD est inaccessible
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Si votre email est enregistré, vous recevrez un lien de réinitialisation'
        ]);
        exit();
    }

    if (!$user) {
        // Pour des raisons de sécurité, on ne révèle pas que l'email n'existe pas
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Si votre email est enregistré, vous recevrez un lien de réinitialisation'
        ]);
        exit();
    }

    // Générer un token sécurisé
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    // Marquer les anciens tokens comme utilisés
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$user['id']]);
    
    // Créer un nouveau token avec expiration à 10 minutes
    $stmt = $db->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt]);

    // Envoyer l'email avec EmailService
    $emailService = new EmailService();
    $emailService->sendPasswordResetEmail($email, $user['first_name'], $token);

    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Si votre email est enregistré, vous recevrez un lien de réinitialisation'
    ]);

} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue. Veuillez réessayer plus tard.'
    ]);
}