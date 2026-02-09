<?php
// backend/classes/JWTHelper.php
// Gestion des tokens JWT signés avec HMAC-SHA256

class JWTHelper {
    
    private static $secret = null;
    private static $expiresIn = 86400; // 24h par défaut

    /**
     * Charger la configuration JWT depuis config.php
     */
    private static function loadConfig() {
        if (self::$secret !== null) return;
        
        $config = require __DIR__ . '/../config/config.php';
        self::$secret = $config['jwt']['secret'];
        
        // Parser la durée d'expiration (ex: '24h', '1h', '30m')
        $expires = $config['jwt']['expires_in'] ?? '24h';
        if (preg_match('/^(\d+)h$/', $expires, $m)) {
            self::$expiresIn = (int)$m[1] * 3600;
        } elseif (preg_match('/^(\d+)m$/', $expires, $m)) {
            self::$expiresIn = (int)$m[1] * 60;
        } elseif (preg_match('/^(\d+)$/', $expires, $m)) {
            self::$expiresIn = (int)$m[1];
        }
    }

    /**
     * Encoder en base64url (sans padding)
     */
    private static function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Décoder du base64url
     */
    private static function base64urlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Générer un token JWT
     * 
     * @param array $payload Données à inclure (user_id, email, role, etc.)
     * @return string Token JWT signé
     */
    public static function generate(array $payload): string {
        self::loadConfig();

        $header = self::base64urlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ]));

        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + self::$expiresIn;

        $payloadEncoded = self::base64urlEncode(json_encode($payload));

        $signature = self::base64urlEncode(
            hash_hmac('sha256', "$header.$payloadEncoded", self::$secret, true)
        );

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Valider et décoder un token JWT
     * 
     * @param string $token Token JWT
     * @return array|false Payload décodé ou false si invalide
     */
    public static function validate(string $token) {
        self::loadConfig();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;

        // Vérifier la signature
        $expectedSignature = self::base64urlEncode(
            hash_hmac('sha256', "$header.$payload", self::$secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Décoder le payload
        $data = json_decode(self::base64urlDecode($payload), true);
        if (!$data) {
            return false;
        }

        // Vérifier l'expiration
        if (isset($data['exp']) && $data['exp'] < time()) {
            return false;
        }

        return $data;
    }

    /**
     * Extraire et valider le token depuis le header Authorization
     * 
     * @return array|false Payload décodé ou false
     */
    public static function getFromRequest() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return false;
        }

        return self::validate($matches[1]);
    }

    /**
     * Exiger un token JWT valide, sinon renvoyer 401
     * 
     * @return array Payload du token
     */
    public static function requireAuth(): array {
        $payload = self::getFromRequest();
        
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token invalide ou expiré']);
            exit();
        }

        return $payload;
    }
}
