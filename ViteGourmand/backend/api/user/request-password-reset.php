<?php
// backend/api/user/request-password-reset.php
// Endpoint simplifié pour demander un reset de mot de passe depuis le profil utilisateur
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';
require_once __DIR__ . '/../../classes/Mailer.php';

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

// Validation de la taille et du type de contenu
if (!InputValidator::validateInputSize()) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Les données envoyées sont trop volumineuses']);
    exit();
}

if (!InputValidator::validateContentType()) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Type de contenu non autorisé']);
    exit();
}

// Protection CSRF
CSRFProtection::requireValidation();

// Rate Limiting
RateLimiter::setRateLimitHeaders('password_reset_request');

if (!RateLimiter::checkLimit('password_reset_request')) {
    $waitTime = RateLimiter::getWaitTime('password_reset_request');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email requis']);
    exit();
}

$email = trim($data['email']);

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'utilisateur existe
    $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    // Générer un token sécurisé
    $token = bin2hex(random_bytes(32));
    $expiresAt = (new \DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    // Marquer les anciens tokens comme utilisés
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$user['id']]);

    // Créer un nouveau token
    $stmt = $db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expiresAt]);

    // Envoyer l'email
    try {
        $config = require __DIR__ . '/../../config/config.php';
        $resetLink = $config['mail']['base_url'] . "/ResetPassword?token=" . $token;

        $emailBody = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Lato', -apple-system, BlinkMacSystemFont, sans-serif; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #2E2E2E; line-height: 1.6; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid rgba(98,125,74,0.1); overflow: hidden; }
                .email-header { background: linear-gradient(135deg, #627D4A, #4a5f38); padding: 40px 30px; text-align: center; color: white; }
                .email-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
                .email-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 10px; }
                .email-header p { font-size: 1rem; opacity: 0.9; }
                .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                .email-body { padding: 40px 30px; }
                .email-body h2 { color: #627D4A; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                .email-body p { margin-bottom: 20px; font-size: 1rem; color: #1A1A1A; }
                .reset-button { display: inline-block; background: linear-gradient(135deg, #627D4A, #4a5f38); color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; font-size: 1rem; text-align: center; box-shadow: 0 4px 15px rgba(98,125,74,0.3); margin: 30px 0; }
                .security-info { background: rgba(98,125,74,0.05); border-left: 4px solid #627D4A; padding: 20px; border-radius: 8px; margin: 30px 0; }
                .security-info h3 { color: #627D4A; font-size: 1rem; margin-bottom: 10px; }
                .security-info p { margin: 0; font-size: 0.9rem; color: #6c757d; }
                .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <div class='email-icon'>🔑</div>
                    <div class='logo'>Vite&Gourmand</div>
                    <h1>Changement de mot de passe</h1>
                    <p>Demande depuis votre espace profil</p>
                </div>
                <div class='email-body'>
                    <h2>Bonjour " . htmlspecialchars($user['first_name']) . ",</h2>
                    <p>Vous avez demandé à changer votre mot de passe depuis votre espace profil. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe.</p>
                    <div style='text-align: center;'>
                        <a href='$resetLink' class='reset-button'>🔐 Définir un nouveau mot de passe</a>
                    </div>
                    <div class='security-info'>
                        <h3>🛡️ Informations importantes</h3>
                        <p><strong>Ce lien expirera dans 10 minutes.</strong> Il ne peut être utilisé qu'une seule fois.</p>
                        <p>Si vous n'avez pas fait cette demande, vous pouvez ignorer cet email.</p>
                    </div>
                    <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
                    <p style='word-break: break-all; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.85rem;'>$resetLink</p>
                </div>
                <div class='email-footer'>
                    <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                    <p>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>";

        Mailer::send($email, 'Changement de mot de passe - Vite&Gourmand', $emailBody);

        echo json_encode([
            'success' => true,
            'message' => 'Un email de réinitialisation vous a été envoyé.'
        ]);

    } catch (Exception $emailError) {
        error_log("Erreur envoi email reset password depuis profil: " . $emailError->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Erreur interne'
    ]);
}
