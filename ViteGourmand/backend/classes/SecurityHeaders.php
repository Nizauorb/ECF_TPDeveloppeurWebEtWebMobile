<?php
// backend/classes/SecurityHeaders.php

class SecurityHeaders {
    
    /**
     * Appliquer tous les headers de sécurité
     */
    public static function apply(): void {
        self::setCSP();
        self::setXSSProtection();
        self::setContentTypeOptions();
        self::setFrameOptions();
        self::setHSTS();
        self::setReferrerPolicy();
        self::setPermissionsPolicy();
    }
    
    /**
     * Content Security Policy - Protection contre XSS et injections
     */
    private static function setCSP(): void {
        // CSP restrictive pour l'API
        $csp = implode('; ', [
            "default-src 'none'",           // Par défaut, rien n'est autorisé
            "script-src 'self'",            // Scripts uniquement du même domaine
            "style-src 'self' 'unsafe-inline'", // Styles du même domaine + inline
            "img-src 'self' data: https:",  // Images du même domaine, data:, HTTPS
            "font-src 'self'",              // Fonts du même domaine
            "connect-src 'self'",           // API calls uniquement au même domaine
            "frame-ancestors 'none'",       // Pas d'embedding dans des frames
            "base-uri 'self'",              // Base URI uniquement même domaine
            "form-action 'self'",           // Forms uniquement vers même domaine
            "upgrade-insecure-requests"     // Forcer HTTPS
        ]);
        
        header("Content-Security-Policy: {$csp}");
    }
    
    /**
     * XSS Protection Header
     */
    private static function setXSSProtection(): void {
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
    }
    
    /**
     * Content Type Options - Empêche le MIME-sniffing
     */
    private static function setContentTypeOptions(): void {
        header("X-Content-Type-Options: nosniff");
    }
    
    /**
     * Frame Options - Protection contre clickjacking
     */
    private static function setFrameOptions(): void {
        header("X-Frame-Options: DENY");
    }
    
    /**
     * HSTS - Forcer HTTPS
     */
    private static function setHSTS(): void {
        // Uniquement en HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $maxAge = 31536000; // 1 an
            $includeSubDomains = true;
            
            header("Strict-Transport-Security: max-age={$maxAge}; includeSubDomains; preload");
        }
    }
    
    /**
     * Referrer Policy - Contrôle des informations de référence
     */
    private static function setReferrerPolicy(): void {
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
    
    /**
     * Permissions Policy - Contrôle des fonctionnalités du navigateur
     */
    private static function setPermissionsPolicy(): void {
        $policies = [
            "geolocation=()",           // Pas de géolocalisation
            "microphone=()",            // Pas de microphone
            "camera=()",                // Pas de caméra
            "payment=()",               // Pas de paiements
            "usb=()",                   // Pas d'USB
            "magnetometer=()",          // Pas de magnétomètre
            "gyroscope=()",             // Pas de gyroscope
            "accelerometer=()",         // Pas d'accéléromètre
            "ambient-light-sensor=()",  // Pas de capteur de lumière
            "autoplay=()",              // Pas d'autoplay
            "encrypted-media=()",       // Pas de média chiffré
            "fullscreen=()",            // Pas de plein écran
            "picture-in-picture=()",    // Pas de picture-in-picture
        ];
        
        header("Permissions-Policy: " . implode(', ', $policies));
    }
    
    /**
     * Headers CORS sécurisés
     */
    public static function setSecureCORS(?string $allowedOrigin = null): void {
        // Origin par défaut ou celui spécifié
        $origin = $allowedOrigin ?? ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000');
        
        // Validation de l'origine
        if (self::isAllowedOrigin($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }
        
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
        header("Access-Control-Max-Age: 86400"); // 24h
        header("Vary: Origin");
    }
    
    /**
     * Valider si l'origine est autorisée
     */
    private static function isAllowedOrigin(string $origin): bool {
        $configFile = __DIR__ . '/../config/security.php';
        if (file_exists($configFile)) {
            $security = require $configFile;
            $allowedOrigins = $security['cors']['allowed_origins'] ?? [];
        } else {
            $allowedOrigins = [
                'http://localhost:3000',
                'https://localhost:3000',
                'http://127.0.0.1:3000',
                'https://127.0.0.1:3000',
            ];
        }
        
        return in_array($origin, $allowedOrigins);
    }
    
    /**
     * Headers de cache sécurisés pour l'API
     */
    public static function setNoCache(): void {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    }
    
    /**
     * Headers pour les réponses d'erreur
     */
    public static function setErrorHeaders(): void {
        self::apply();
        self::setNoCache();
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    /**
     * Headers pour les réponses de succès
     */
    public static function setSuccessHeaders(): void {
        self::apply();
        self::setNoCache();
        header("Content-Type: application/json; charset=UTF-8");
    }
    
    /**
     * Headers pour les requêtes OPTIONS (pre-flight)
     */
    public static function setOptionsHeaders(): void {
        header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost:3000'));
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
        header("Access-Control-Max-Age: 86400");
        header("Access-Control-Allow-Credentials: true");
        header("Content-Length: 0");
        header("Content-Type: text/plain");
    }
}
