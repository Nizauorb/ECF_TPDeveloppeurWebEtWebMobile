<?php
// backend/classes/EmailService.php

class EmailService {
    private $config;
    private $mail;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        
        // Importer PHPMailer
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $this->mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->setupMailer();
    }

    private function setupMailer() {
        try {
            // Configuration SMTP depuis config.php
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['mail']['host'];
            $this->mail->Port = $this->config['mail']['port'];
            $this->mail->SMTPAuth = $this->config['mail']['smtp_auth'];
            $this->mail->CharSet = $this->config['mail']['charset'];
            
            // Configuration pour MailHog (développement)
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Expéditeur par défaut
            $this->mail->setFrom(
                $this->config['mail']['from_email'], 
                $this->config['mail']['from_name']
            );

        } catch (Exception $e) {
            error_log("Erreur configuration EmailService: " . $e->getMessage());
            throw new Exception("Impossible de configurer le service email");
        }
    }

    /**
     * Envoyer un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
        try {
            $this->mail->addAddress($userEmail);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Réinitialisation de votre mot de passe - Vite&Gourmand';

            $resetLink = $this->config['mail']['base_url'] . "/ResetPassword?token=" . $resetToken;
            
            $this->mail->Body = $this->getPasswordResetTemplate($userName, $resetLink);
            $this->mail->AltBody = $this->getPasswordResetTextTemplate($userName, $resetLink);

            return $this->mail->send();

        } catch (Exception $e) {
            error_log("Erreur envoi email reset password: " . $e->getMessage());
            throw new Exception("Erreur lors de l'envoi de l'email de réinitialisation");
        }
    }

    /**
     * Envoyer un email de contact à l'entreprise
     */
    public function sendContactEmail($name, $email, $message) {
        try {
            // Destinataire = email de l'entreprise
            $this->mail->addAddress($this->config['mail']['admin_email']);
            
            // Répondre au client
            $this->mail->addReplyTo($email, $name);
            
            $this->mail->isHTML(true);
            $this->mail->Subject = "Nouveau message de contact de {$name} - {$email}";

            $this->mail->Body = $this->getContactTemplate($name, $email, $message);
            $this->mail->AltBody = $this->getContactTextTemplate($name, $email, $message);

            return $this->mail->send();

        } catch (Exception $e) {
            error_log("Erreur envoi email contact: " . $e->getMessage());
            throw new Exception("Erreur lors de l'envoi de l'email de contact");
        }
    }

    /**
     * Template HTML pour email de réinitialisation
     */
    private function getPasswordResetTemplate($userName, $resetLink) {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Réinitialisation de mot de passe</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #2E2E2E; line-height: 1.6; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(98, 125, 74, 0.1); overflow: hidden; }
                .email-header { background: linear-gradient(135deg, #627D4A, #4a5f38); padding: 40px 30px; text-align: center; color: white; }
                .email-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
                .email-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 10px; }
                .email-header p { font-size: 1rem; opacity: 0.9; }
                .email-body { padding: 40px 30px; }
                .email-body h2 { color: #627D4A; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                .email-body p { margin-bottom: 20px; font-size: 1rem; color: #1A1A1A; }
                .reset-button { display: inline-block; background: linear-gradient(135deg, #627D4A, #4a5f38); color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: 600; font-size: 1rem; text-align: center; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(98, 125, 74, 0.3); margin: 30px 0; }
                .reset-button:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(98, 125, 74, 0.4); }
                .security-info { background: rgba(98, 125, 74, 0.05); border-left: 4px solid #627D4A; padding: 20px; border-radius: 8px; margin: 30px 0; }
                .security-info h3 { color: #627D4A; font-size: 1rem; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
                .security-info p { margin: 0; font-size: 0.9rem; color: #6c757d; }
                .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
                .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                @media (max-width: 600px) { body { padding: 10px; } .email-container { border-radius: 12px; } .email-header { padding: 30px 20px; } .email-body { padding: 30px 20px; } .email-footer { padding: 20px; } .reset-button { padding: 12px 24px; font-size: 0.9rem; } }
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
                    <h2>Bonjour " . htmlspecialchars($userName) . ",</h2>
                    
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
        </html>";
    }

    /**
     * Template texte pour email de réinitialisation
     */
    private function getPasswordResetTextTemplate($userName, $resetLink) {
        return "Bonjour {$userName},\n\n" .
               "Vous avez demandé à réinitialiser votre mot de passe pour votre compte Vite&Gourmand.\n\n" .
               "Cliquez sur ce lien pour définir un nouveau mot de passe : {$resetLink}\n\n" .
               "Ce lien expirera dans 10 minutes et ne peut être utilisé qu'une seule fois.\n\n" .
               "Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.\n\n" .
               "© 2024 Vite&Gourmand - Tous droits réservés";
    }

    /**
     * Template HTML pour email de contact
     */
    private function getContactTemplate($name, $email, $message) {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Nouveau message de contact</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #2E2E2E; line-height: 1.6; padding: 20px; }
                .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); border: 1px solid rgba(98, 125, 74, 0.1); overflow: hidden; }
                .email-header { background: linear-gradient(135deg, #627D4A, #4a5f38); padding: 40px 30px; text-align: center; color: white; }
                .email-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
                .email-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 10px; }
                .email-header p { font-size: 1rem; opacity: 0.9; }
                .email-body { padding: 40px 30px; }
                .email-body h2 { color: #627D4A; font-size: 1.25rem; margin-bottom: 20px; font-weight: 600; }
                .contact-info { background: rgba(98, 125, 74, 0.05); border-left: 4px solid #627D4A; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .contact-info p { margin: 8px 0; font-size: 0.95rem; }
                .contact-info strong { color: #627D4A; }
                .message-content { background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6c757d; }
                .message-content p { margin: 0; white-space: pre-wrap; font-size: 1rem; line-height: 1.7; }
                .email-footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .email-footer p { margin: 0; font-size: 0.85rem; color: #6c757d; }
                .logo { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 10px; }
                @media (max-width: 600px) { body { padding: 10px; } .email-container { border-radius: 12px; } .email-header { padding: 30px 20px; } .email-body { padding: 30px 20px; } .email-footer { padding: 20px; } }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <div class='email-icon'>📧</div>
                    <div class='logo'>Vite&Gourmand</div>
                    <h1>Nouveau message de contact</h1>
                    <p>Un client vous a contacté via le formulaire</p>
                </div>
                
                <div class='email-body'>
                    <h2>Informations du contact</h2>
                    
                    <div class='contact-info'>
                        <p><strong>Nom :</strong> " . htmlspecialchars($name) . "</p>
                        <p><strong>Email :</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Date :</strong> " . date('d/m/Y H:i') . "</p>
                    </div>
                    
                    <h2>Message</h2>
                    
                    <div class='message-content'>
                        <p>" . htmlspecialchars($message) . "</p>
                    </div>
                    
                    <p style='text-align: center; margin-top: 30px;'>
                        <a href='mailto:" . htmlspecialchars($email) . "' style='display: inline-block; background: linear-gradient(135deg, #627D4A, #4a5f38); color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600;'>
                            📧 Répondre au client
                        </a>
                    </p>
                </div>
                
                <div class='email-footer'>
                    <p>Cet email a été généré automatiquement via le formulaire de contact du site.</p>
                    <p>© 2024 Vite&Gourmand - Tous droits réservés</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Template texte pour email de contact
     */
    private function getContactTextTemplate($name, $email, $message) {
        return "NOUVEAU MESSAGE DE CONTACT\n\n" .
               "Informations du contact :\n" .
               "Nom : {$name}\n" .
               "Email : {$email}\n" .
               "Date : " . date('d/m/Y H:i') . "\n\n" .
               "Message :\n{$message}\n\n" .
               "© 2024 Vite&Gourmand - Tous droits réservés";
    }
}
