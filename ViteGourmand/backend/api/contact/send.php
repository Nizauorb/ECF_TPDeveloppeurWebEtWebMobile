<?php
// backend/api/contact/send.php

error_reporting(E_ALL);
ini_set('display_errors', 0); // Désactiver l'affichage des warnings en production

// Inclure les dépendances
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

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

// Rate Limiting - Sécurité anti-spam
RateLimiter::setRateLimitHeaders('contact');

if (!RateLimiter::checkLimit('contact')) {
    $waitTime = RateLimiter::getWaitTime('contact');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de demandes. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

try {
    // Lire les données JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validation des champs avec InputValidator
    $validation = InputValidator::validateContactData($data);
    
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données invalides',
            'errors' => $validation['errors']
        ]);
        exit();
    }
    
    // Utiliser les données assainies
    $name = $validation['sanitized']['name'];
    $email = $validation['sanitized']['email'];
    $message = $validation['sanitized']['message'];

    // Envoyer l'email (version directe sans EmailService)
    try {
        error_log("Envoi email contact direct...");
        
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
        
        // Expéditeur
        $mail->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        
        // Destinataire = email de l'entreprise
        $mail->addAddress($config['mail']['admin_email']);
        
        // Répondre au client
        $mail->addReplyTo($email, $name);
        
        $mail->isHTML(true);
        $mail->Subject = "Nouveau message de contact de {$name} - {$email}";

        // Template HTML moderne
        $mail->Body = "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nouveau message de contact</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>Vite & Gourmand</h1>
                <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0;'>Nouveau message de contact</p>
            </div>
            
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #333; margin-top: 0;'>Message reçu</h2>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea;'>
                    <p style='margin: 0 0 10px 0;'><strong>Nom :</strong> {$name}</p>
                    <p style='margin: 0 0 15px 0;'><strong>Email :</strong> {$email}</p>
                    <div style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>
                        <p style='margin: 0 0 5px 0;'><strong>Message :</strong></p>
                        <p style='margin: 0; white-space: pre-wrap;'>{$message}</p>
                    </div>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='mailto:{$email}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; text-decoration: none; border-radius: 50px; font-weight: bold; display: inline-block;'>Répondre au client</a>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
                    <p style='color: #666; font-size: 12px; margin: 0;'>
                        <strong>Date de réception :</strong> " . date('d/m/Y H:i:s') . "<br>
                        <strong>IP :</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Inconnue') . "
                    </p>
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #999; font-size: 12px;'>
                <p>&copy; 2024 Vite & Gourmand. Tous droits réservés.</p>
            </div>
        </body>
        </html>";
        
        // Version texte
        $mail->AltBody = "Vite & Gourmand - Nouveau message de contact\n\n" .
               "Informations du client :\n" .
               "Nom : {$name}\n" .
               "Email : {$email}\n" .
               "Date : " . date('d/m/Y H:i:s') . "\n" .
               "IP : " . ($_SERVER['REMOTE_ADDR'] ?? 'Inconnue') . "\n\n" .
               "Message :\n" .
               "{$message}\n\n" .
               "© 2024 Vite & Gourmand. Tous droits réservés.";

        $result = $mail->send();
        error_log("Email contact envoyé avec succès: " . ($result ? 'true' : 'false'));
        
    } catch (Exception $emailError) {
        error_log("Erreur envoi email contact: " . $emailError->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard.'
        ]);
        exit();
    }

    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.'
    ]);

} catch (Exception $e) {
    error_log("Erreur dans contact/send.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer plus tard.'
    ]);
}
