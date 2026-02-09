<?php
// backend/classes/Mailer.php
// Classe unifiée d'envoi de mail : PHPMailer en local, API Resend en production

class Mailer {
    private static $config = null;
    private static $isLocal = null;

    private static function loadConfig(): void {
        if (self::$config !== null) return;
        self::$config = require __DIR__ . '/../config/config.php';
        self::$isLocal = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1']);
    }

    /**
     * Envoyer un email
     * @param string $to Adresse du destinataire
     * @param string $subject Sujet du mail
     * @param string $htmlBody Corps HTML du mail
     * @param string|null $replyTo Adresse de réponse (optionnel)
     * @param string|null $replyToName Nom pour le reply-to (optionnel)
     * @return bool true si envoyé avec succès
     * @throws Exception en cas d'erreur
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $replyToName = null): bool {
        self::loadConfig();

        if (self::$isLocal) {
            return self::sendWithPHPMailer($to, $subject, $htmlBody, $replyTo, $replyToName);
        } else {
            return self::sendWithResend($to, $subject, $htmlBody, $replyTo, $replyToName);
        }
    }

    /**
     * Envoi via PHPMailer (développement local avec MailHog)
     */
    private static function sendWithPHPMailer(string $to, string $subject, string $htmlBody, ?string $replyTo, ?string $replyToName): bool {
        require_once __DIR__ . '/../vendor/autoload.php';

        $mailConfig = self::$config['mail'];
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $mailConfig['host'];
        $mail->Port = $mailConfig['port'];
        $mail->SMTPAuth = $mailConfig['smtp_auth'];
        if (!empty($mailConfig['smtp_secure'])) $mail->SMTPSecure = $mailConfig['smtp_secure'];
        if (!empty($mailConfig['username'])) $mail->Username = $mailConfig['username'];
        if (!empty($mailConfig['password'])) $mail->Password = $mailConfig['password'];
        $mail->CharSet = $mailConfig['charset'];

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($to);

        if ($replyTo) {
            $mail->addReplyTo($replyTo, $replyToName ?? $replyTo);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        return true;
    }

    /**
     * Envoi via l'API HTTP de Resend (production)
     */
    private static function sendWithResend(string $to, string $subject, string $htmlBody, ?string $replyTo, ?string $replyToName): bool {
        $mailConfig = self::$config['mail'];
        $apiKey = $mailConfig['resend_api_key'] ?? $mailConfig['password'];

        $payload = [
            'from' => $mailConfig['from_name'] . ' <' . $mailConfig['from_email'] . '>',
            'to' => [$to],
            'subject' => $subject,
            'html' => $htmlBody,
        ];

        if ($replyTo) {
            $payload['reply_to'] = $replyTo;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Resend curl error: " . $curlError);
            throw new Exception("Erreur de connexion au service d'envoi d'email");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Resend OK: email envoyé à {$to} (id: " . ($result['id'] ?? 'unknown') . ")");
            return true;
        }

        $errorMsg = $result['message'] ?? $response;
        error_log("Resend error ({$httpCode}): {$errorMsg}");
        throw new Exception("Erreur envoi email: " . $errorMsg);
    }
}
