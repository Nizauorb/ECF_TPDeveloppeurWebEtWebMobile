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
    require_once __DIR__ . '/../../vendor/autoload.php';
    $config = require __DIR__ . '/../../config/config.php';

    // Récupérer l'email actuel de l'utilisateur
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    $currentEmail = $currentUser['email'];
    $emailChanged = (strtolower($email) !== strtolower($currentEmail));

    // Si l'email change, vérifier qu'il n'est pas déjà pris
    if ($emailChanged) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé par un autre compte']);
            exit();
        }
    }

    // Mettre à jour les champs SANS l'email (l'email actuel reste)
    $stmt = $db->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, phone = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([$firstName, $lastName, $phone, $address, $userId]);

    // Si l'email a changé : envoyer un code de vérification à l'ANCIENNE adresse
    if ($emailChanged) {
        // Générer un code à 6 chiffres
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        // Invalider les anciennes demandes
        $stmt = $db->prepare("UPDATE email_change_requests SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->execute([$userId]);

        // Stocker la demande
        $stmt = $db->prepare("
            INSERT INTO email_change_requests (user_id, new_email, verification_code, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $email, $verificationCode, $expiresAt]);

        // Envoyer le code par email à l'ANCIENNE adresse
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['mail']['host'];
            $mail->Port = $config['mail']['port'];
            $mail->SMTPAuth = $config['mail']['smtp_auth'];
            $mail->CharSet = $config['mail']['charset'];
            $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
            $mail->addAddress($currentEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Vérification de changement d\'email - Vite&Gourmand';

            $mail->Body = "
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
                    .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                    .email-body { padding: 40px 30px; }
                    .email-body h2 { color: #627D4A; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                    .email-body p { margin-bottom: 15px; font-size: 1rem; color: #1A1A1A; }
                    .code-box { background: #f8f9fa; border: 2px dashed #627D4A; padding: 20px; border-radius: 12px; text-align: center; margin: 25px 0; }
                    .code-box .code { font-size: 2.5rem; font-weight: 700; color: #627D4A; letter-spacing: 8px; font-family: monospace; }
                    .security-info { background: rgba(255,193,7,0.1); border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .security-info p { margin: 5px 0; font-size: 0.9rem; color: #6c757d; }
                    .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                    .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <div class='email-icon'>📧</div>
                        <div class='logo'>Vite&Gourmand</div>
                        <h1>Changement d'adresse email</h1>
                    </div>
                    <div class='email-body'>
                        <h2>Bonjour " . htmlspecialchars($firstName) . ",</h2>
                        <p>Une demande de changement d'adresse email a été effectuée sur votre compte.</p>
                        <p><strong>Nouvelle adresse demandée :</strong> " . htmlspecialchars($email) . "</p>
                        <p>Pour confirmer ce changement, saisissez le code suivant dans votre espace profil :</p>
                        <div class='code-box'>
                            <div class='code'>{$verificationCode}</div>
                        </div>
                        <div class='security-info'>
                            <p><strong>⚠️ Ce code expire dans 15 minutes.</strong></p>
                            <p>Si vous n'avez pas demandé ce changement, ignorez cet email. Votre adresse email restera inchangée.</p>
                        </div>
                    </div>
                    <div class='email-footer'>
                        <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                        <p>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Bonjour {$firstName},\n\nCode de vérification pour changer votre email vers {$email} : {$verificationCode}\n\nCe code expire dans 15 minutes.";
            $mail->send();
        } catch (Exception $emailError) {
            error_log("Erreur envoi email vérification changement email: " . $emailError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'email_change_pending' => true,
            'message' => 'Profil mis à jour. Un code de vérification a été envoyé à votre ancienne adresse email pour confirmer le changement.'
        ]);
    } else {
        // Pas de changement d'email : envoyer un mail de confirmation classique
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $config['mail']['host'];
            $mail->Port = $config['mail']['port'];
            $mail->SMTPAuth = $config['mail']['smtp_auth'];
            $mail->CharSet = $config['mail']['charset'];
            $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
            $mail->addAddress($currentEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Confirmation de modification de profil - Vite&Gourmand';

            $mail->Body = "
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
                    .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                    .email-body { padding: 40px 30px; }
                    .email-body h2 { color: #627D4A; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                    .email-body p { margin-bottom: 15px; font-size: 1rem; color: #1A1A1A; }
                    .info-box { background: rgba(98,125,74,0.05); border-left: 4px solid #627D4A; padding: 20px; border-radius: 8px; margin: 20px 0; }
                    .info-box p { margin: 5px 0; font-size: 0.95rem; }
                    .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                    .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <div class='email-icon'>✅</div>
                        <div class='logo'>Vite&Gourmand</div>
                        <h1>Profil modifié</h1>
                    </div>
                    <div class='email-body'>
                        <h2>Bonjour " . htmlspecialchars($firstName) . ",</h2>
                        <p>Vos informations de profil ont été mises à jour avec succès.</p>
                        <div class='info-box'>
                            <p><strong>Nom :</strong> " . htmlspecialchars($lastName) . "</p>
                            <p><strong>Prénom :</strong> " . htmlspecialchars($firstName) . "</p>
                            <p><strong>Email :</strong> " . htmlspecialchars($currentEmail) . "</p>
                            <p><strong>Téléphone :</strong> " . htmlspecialchars($phone ?: 'Non renseigné') . "</p>
                            <p><strong>Adresse :</strong> " . htmlspecialchars($address ?: 'Non renseignée') . "</p>
                        </div>
                        <p>Si vous n'êtes pas à l'origine de cette modification, veuillez contacter notre support immédiatement.</p>
                    </div>
                    <div class='email-footer'>
                        <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                        <p>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->AltBody = "Bonjour {$firstName},\n\nVos informations de profil ont été mises à jour.\nNom: {$lastName}\nPrénom: {$firstName}\nEmail: {$currentEmail}\nTéléphone: {$phone}\nAdresse: {$address}";
            $mail->send();
        } catch (Exception $emailError) {
            error_log("Erreur envoi email confirmation profil: " . $emailError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès. Un email de confirmation vous a été envoyé.'
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
