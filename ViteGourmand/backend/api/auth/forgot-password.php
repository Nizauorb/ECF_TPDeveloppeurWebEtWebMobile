<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des warnings en production

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

// Inclure les dépendances
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';

// Validation de la taille et du type de contenu
if (!InputValidator::validateInputSize()) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'message' => 'Les données envoyées sont trop volumineuses'
    ]);
    exit();
}

if (!InputValidator::validateContentType()) {
    http_response_code(415);
    echo json_encode([
        'success' => false,
        'message' => 'Type de contenu non autorisé'
    ]);
    exit();
}

// Protection CSRF - Sécurité contre attaques cross-site
CSRFProtection::requireValidation();

// Rate Limiting - Sécurité anti-bruteforce
RateLimiter::setRateLimitHeaders('forgot_password');

if (!RateLimiter::checkLimit('forgot_password')) {
    $waitTime = RateLimiter::getWaitTime('forgot_password');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

try {
    // Récupérer les données JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validation de l'email avec InputValidator
    if (empty($data['email'])) {
        throw new Exception('Email est requis');
    }
    
    $emailValidation = InputValidator::validateEmail($data['email']);
    if (!$emailValidation['valid']) {
        throw new Exception($emailValidation['error']);
    }
    
    $email = $emailValidation['sanitized'];

    // Vérifier si l'utilisateur existe
    try {
        error_log("Tentative de connexion à la base de données...");
        $db = Database::getInstance()->getConnection();
        error_log("Connexion BDD réussie");
        
        $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        error_log("Recherche utilisateur: " . ($user ? "trouvé" : "non trouvé"));
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
    $expiresAt = (new \DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    // Marquer les anciens tokens comme utilisés
    $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
    $stmt->execute([$user['id']]);
    
    // Créer un nouveau token avec expiration à 10 minutes
    $stmt = $db->prepare("
        INSERT INTO password_reset_tokens (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt]);

    // Envoyer l'email (version directe sans EmailService pour tester)
    try {
        error_log("Test envoi email direct...");
        
        // Importer PHPMailer directement
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $config = require __DIR__ . '/../../config/config.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = $config['mail']['host'];
        $mail->Port = $config['mail']['port'];
        $mail->SMTPAuth = $config['mail']['smtp_auth'];
        $mail->CharSet = $config['mail']['charset'];
        
        // Expéditeur et destinataire
        $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe - Vite&Gourmand';

        $resetLink = $config['mail']['base_url'] . "/ResetPassword?token=" . $token;
        
        // Template simple
        $mail->Body = "
        <h2>Bonjour {$user['first_name']},</h2>
        <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous :</p>
        <p><a href='{$resetLink}'>Réinitialiser mon mot de passe</a></p>
        <p>Ou copiez ce lien : {$resetLink}</p>
        <p>Ce lien expire dans 10 minutes.</p>
        ";
        
        $mail->AltBody = "Bonjour {$user['first_name']},\n\nRéinitialisez votre mot de passe ici : {$resetLink}\n\nCe lien expire dans 10 minutes.";

        $result = $mail->send();
        error_log("Email envoyé avec succès: " . ($result ? 'true' : 'false'));
        
    } catch (Exception $emailError) {
        error_log("Erreur envoi email direct: " . $emailError->getMessage());
        // Continue quand même pour des raisons de sécurité
    }

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