<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/Database.php';

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
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

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

    // Configuration de PHPMailer pour MailHog
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'localhost';
    $mail->Port = 1025;
    $mail->SMTPAuth = false;
    $mail->CharSet = 'UTF-8';
    
    // Désactiver la vérification SSL pour le développement
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    // Expéditeur et destinataire
    $mail->setFrom('no-reply@vite-gourmand.local', 'Vite&Gourmand');
    $mail->addAddress($email);

    // Contenu de l'email
    $resetLink = "http://localhost:3000/ResetPassword?token=" . $token;

    $mail->isHTML(true);
    $mail->Subject = 'Réinitialisation de votre mot de passe - Vite&Gourmand';
    
    // Design moderne correspondant aux pages d'authentification
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Réinitialisation de mot de passe</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                color: #2E2E2E;
                line-height: 1.6;
                padding: 20px;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
                border: 1px solid rgba(98, 125, 74, 0.1);
                overflow: hidden;
            }
            
            .email-header {
                background: linear-gradient(135deg, #627D4A, #4a5f38);
                padding: 40px 30px;
                text-align: center;
                color: white;
            }
            
            .email-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
            }
            
            .email-header h1 {
                font-size: 1.75rem;
                font-weight: 700;
                margin-bottom: 10px;
            }
            
            .email-header p {
                font-size: 1rem;
                opacity: 0.9;
            }
            
            .email-body {
                padding: 40px 30px;
            }
            
            .email-body h2 {
                color: #627D4A;
                font-size: 1.25rem;
                margin-bottom: 20px;
                font-weight: 600;
            }
            
            .email-body p {
                margin-bottom: 20px;
                font-size: 1rem;
                color: #1A1A1A;
            }
            
            .reset-button {
                display: inline-block;
                background: linear-gradient(135deg, #627D4A, #4a5f38);
                color: white;
                text-decoration: none;
                padding: 15px 30px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 1rem;
                text-align: center;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(98, 125, 74, 0.3);
                margin: 30px 0;
            }
            
            .reset-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(98, 125, 74, 0.4);
            }
            
            .security-info {
                background: rgba(98, 125, 74, 0.05);
                border-left: 4px solid #627D4A;
                padding: 20px;
                border-radius: 8px;
                margin: 30px 0;
            }
            
            .security-info h3 {
                color: #627D4A;
                font-size: 1rem;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .security-info p {
                margin: 0;
                font-size: 0.9rem;
                color: #6c757d;
            }
            
            .email-footer {
                background: #f8f9fa;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #e9ecef;
            }
            
            .email-footer p {
                margin: 0;
                font-size: 0.85rem;
                color: #6c757d;
            }
            
            .logo {
                font-size: 1.5rem;
                font-weight: 700;
                color: white;
                margin-bottom: 10px;
            }
            
            @media (max-width: 600px) {
                body {
                    padding: 10px;
                }
                
                .email-container {
                    border-radius: 12px;
                }
                
                .email-header {
                    padding: 30px 20px;
                }
                
                .email-body {
                    padding: 30px 20px;
                }
                
                .email-footer {
                    padding: 20px;
                }
                
                .reset-button {
                    padding: 12px 24px;
                    font-size: 0.9rem;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='email-header'>
                <div class='email-icon'>🔑</div>
                <div class='logo'>Vite&Gourmand</div>
                <h1>Réinitialisation de mot de passe</h1>
                <p>Sécurisez votre accès à votre compte</p>
            </div>
            
            <div class='email-body'>
                <h2>Bonjour " . htmlspecialchars($user['first_name']) . ",</h2>
                
                <p>Vous avez demandé à réinitialiser votre mot de passe pour votre compte Vite&Gourmand. Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe sécurisé.</p>
                
                <div style='text-align: center;'>
                    <a href='$resetLink' class='reset-button'>
                        🔐 Réinitialiser mon mot de passe
                    </a>
                </div>
                
                <div class='security-info'>
                    <h3>🛡️ Informations importantes</h3>
                    <p><strong>Ce lien expirera dans 10 minutes.</strong> Pour des raisons de sécurité, ce lien ne peut être utilisé qu'une seule fois.</p>
                    <p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.</p>
                </div>
                
                <p>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
                <p style='word-break: break-all; background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.85rem;'>$resetLink</p>
            </div>
            
            <div class='email-footer'>
                <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                <p>© 2024 Vite&Gourmand - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Envoyer l'email
    if ($mail->send()) {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'Si votre email est enregistré, vous recevrez un lien de réinitialisation'
        ]);
    } else {
        throw new Exception("Erreur lors de l'envoi de l'email");
    }

} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue. Veuillez réessayer plus tard.'
    ]);
}