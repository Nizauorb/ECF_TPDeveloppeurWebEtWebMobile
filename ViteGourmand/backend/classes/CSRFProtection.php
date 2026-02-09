<?php
// backend/classes/CSRFProtection.php

class CSRFProtection {
    /**
     * Configurer les cookies de session pour la production
     */
    private static function configureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    /**
     * Générer un token CSRF sécurisé
     */
    public static function generateToken(): string {
        self::configureSession();
        
        // Générer un token aléatoire sécurisé
        $token = bin2hex(random_bytes(32));
        
        // Stocker le token en session avec timestamp
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Charger la durée de vie du token depuis la config
     */
    private static function getTokenLifetime(): int {
        $configFile = __DIR__ . '/../config/security.php';
        if (file_exists($configFile)) {
            $security = require $configFile;
            return $security['csrf']['token_lifetime'] ?? 3600;
        }
        return 3600;
    }
    
    /**
     * Valider un token CSRF
     */
    public static function validateToken(string $token): bool {
        self::configureSession();
        
        // Vérifier si le token existe en session
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            error_log("CSRF: Token non trouvé en session");
            return false;
        }
        
        // Vérifier si le token correspond
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF: Token invalide - tentative d'attaque ?");
            return false;
        }
        
        // Vérifier l'âge du token
        $maxAge = self::getTokenLifetime();
        if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
            error_log("CSRF: Token expiré");
            self::clearToken();
            return false;
        }
        
        return true;
    }
    
    /**
     * Nettoyer le token CSRF
     */
    public static function clearToken(): void {
        self::configureSession();
        
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
    }
    
    /**
     * Obtenir le token CSRF actuel (ou en générer un nouveau)
     */
    public static function getToken(): string {
        self::configureSession();
        
        // Générer un nouveau token si inexistant ou expiré
        $maxAge = self::getTokenLifetime();
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) || 
            time() - $_SESSION['csrf_token_time'] > $maxAge) {
            return self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Ajouter le token CSRF aux headers de réponse
     */
    public static function setTokenHeader(): void {
        $token = self::getToken();
        header("X-CSRF-Token: {$token}");
    }
    
    /**
     * Vérifier le token dans les headers ou le corps de la requête
     */
    public static function checkRequest(): bool {
        // Vérifier d'abord dans les headers
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        // Si pas dans les headers, vérifier dans le corps JSON
        if (empty($token)) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? '';
        }
        
        // Si toujours vide, vérifier en POST
        if (empty($token)) {
            $token = $_POST['csrf_token'] ?? '';
        }
        
        if (empty($token)) {
            error_log("CSRF: Aucun token fourni dans la requête");
            return false;
        }
        
        return self::validateToken($token);
    }
    
    /**
     * Middleware pour valider les requêtes POST
     */
    public static function requireValidation(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            if (!self::checkRequest()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Requête invalide - token CSRF manquant ou invalide'
                ]);
                exit();
            }
        }
    }
}
