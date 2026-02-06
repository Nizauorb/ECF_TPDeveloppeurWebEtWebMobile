<?php
// backend/api/user/request-delete-account.php
// Étape 1 : envoie un code de vérification par email pour confirmer la suppression
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/Database.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
    exit();
}

$userId = intval($data['user_id']);

try {
    $db = Database::getInstance()->getConnection();
    $config = require __DIR__ . '/../../config/config.php';

    // Récupérer les infos de l'utilisateur
    $stmt = $db->prepare("SELECT id, email, first_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    // Générer un code à 6 chiffres
    $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = (new \DateTime('+15 minutes'))->format('Y-m-d H:i:s');

    // Invalider les anciennes demandes
    $stmt = $db->prepare("UPDATE account_deletion_requests SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$userId]);

    // Stocker la demande
    $stmt = $db->prepare("
        INSERT INTO account_deletion_requests (user_id, verification_code, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $verificationCode, $expiresAt]);

    // Envoyer le code par email
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['mail']['host'];
        $mail->Port = $config['mail']['port'];
        $mail->SMTPAuth = $config['mail']['smtp_auth'];
        $mail->CharSet = $config['mail']['charset'];
        $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $mail->addAddress($user['email']);
        $mail->isHTML(true);
        $mail->Subject = 'Confirmation de suppression de compte - Vite&Gourmand';

        $mail->Body = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Lato', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #2E2E2E; line-height: 1.6; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid rgba(220,53,69,0.2); overflow: hidden; }
                .email-header { background: linear-gradient(135deg, #dc3545, #b02a37); padding: 40px 30px; text-align: center; color: white; }
                .email-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
                .email-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 10px; }
                .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                .email-body { padding: 40px 30px; }
                .email-body h2 { color: #dc3545; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                .email-body p { margin-bottom: 15px; font-size: 1rem; color: #1A1A1A; }
                .code-box { background: #fff5f5; border: 2px dashed #dc3545; padding: 20px; border-radius: 12px; text-align: center; margin: 25px 0; }
                .code-box .code { font-size: 2.5rem; font-weight: 700; color: #dc3545; letter-spacing: 8px; font-family: monospace; }
                .warning-info { background: rgba(220,53,69,0.05); border-left: 4px solid #dc3545; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .warning-info p { margin: 5px 0; font-size: 0.9rem; color: #6c757d; }
                .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <div class='email-icon'>⚠️</div>
                    <div class='logo'>Vite&Gourmand</div>
                    <h1>Suppression de compte</h1>
                </div>
                <div class='email-body'>
                    <h2>Bonjour " . htmlspecialchars($user['first_name']) . ",</h2>
                    <p>Vous avez demandé la suppression définitive de votre compte Vite&Gourmand.</p>
                    <p>Pour confirmer cette action, saisissez le code suivant dans votre espace profil :</p>
                    <div class='code-box'>
                        <div class='code'>{$verificationCode}</div>
                    </div>
                    <div class='warning-info'>
                        <p><strong>⚠️ Cette action est irréversible.</strong></p>
                        <p>Toutes vos données seront définitivement supprimées : informations personnelles, historique de commandes, préférences.</p>
                        <p><strong>Ce code expire dans 15 minutes.</strong></p>
                        <p>Si vous n'avez pas demandé cette suppression, ignorez cet email.</p>
                    </div>
                </div>
                <div class='email-footer'>
                    <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                    <p>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->AltBody = "Bonjour {$user['first_name']},\n\nCode de confirmation pour supprimer votre compte : {$verificationCode}\n\nCe code expire dans 15 minutes. Cette action est irréversible.";

        $mail->send();
    } catch (Exception $emailError) {
        error_log("Erreur envoi email suppression compte: " . $emailError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Un code de vérification a été envoyé à votre adresse email.'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}
