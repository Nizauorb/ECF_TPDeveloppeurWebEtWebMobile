<?php
// backend/classes/RateLimiter.php

class RateLimiter {
    private static $config = null;
    
    /**
     * Charger la configuration depuis config/security.php
     */
    private static $configByRole = null;
    
    private static function loadConfig(): void {
        if (self::$config !== null) return;
        
        $configFile = __DIR__ . '/../config/security.php';
        if (file_exists($configFile)) {
            $security = require $configFile;
            self::$config = $security['rate_limits'] ?? [];
            self::$configByRole = $security['rate_limits_by_role'] ?? [];
        } else {
            self::$config = [];
            self::$configByRole = [];
        }
    }
    
    /**
     * Obtenir la limite pour une action donnée
     */
    private static function getLimit(string $action): array {
        self::loadConfig();
        
        // Lire la limite par défaut depuis la config ou fallback
        $configFile = __DIR__ . '/../config/security.php';
        $default = ['requests' => 1, 'window' => 60];
        if (file_exists($configFile)) {
            $security = require $configFile;
            $default = $security['rate_limit_default'] ?? $default;
        }
        
        return self::$config[$action] ?? $default;
    }
    
    /**
     * Vérifier si une adresse IP peut faire une requête
     */
    public static function checkLimit(string $action, ?string $ip = null): bool {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $config = self::getLimit($action);
        
        $key = "rate_limit_{$action}_{$ip}";
        $current = self::getStorage($key);
        
        if ($current === null) {
            self::setStorage($key, ['count' => 1, 'start' => time()]);
            return true;
        }
        
        // Vérifier si la fenêtre est expirée
        if (time() - $current['start'] > $config['window']) {
            self::setStorage($key, ['count' => 1, 'start' => time()]);
            return true;
        }
        
        // Vérifier si la limite est dépassée
        if ($current['count'] >= $config['requests']) {
            $remainingTime = $config['window'] - (time() - $current['start']);
            error_log("Rate limit exceeded for {$action} from {$ip}. Try again in {$remainingTime}s");
            return false;
        }
        
        // Incrémenter le compteur
        $current['count']++;
        self::setStorage($key, $current);
        return true;
    }
    
    /**
     * Obtenir le temps d'attente restant
     */
    public static function getWaitTime(string $action, ?string $ip = null): int {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $config = self::getLimit($action);
        
        $key = "rate_limit_{$action}_{$ip}";
        $current = self::getStorage($key);
        
        if ($current === null) return 0;
        
        $elapsed = time() - $current['start'];
        return max(0, $config['window'] - $elapsed);
    }
    
    /**
     * Stockage simple en session (pourrait être remplacé par Redis)
     */
    private static function getStorage(string $key): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION[$key] ?? null;
    }
    
    private static function setStorage(string $key, array $data): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION[$key] = $data;
    }
    
    /**
     * Headers HTTP pour rate limiting
     */
    public static function setRateLimitHeaders(string $action): void {
        $config = self::getLimit($action);
        $remaining = $config['requests'] - (self::getStorage("rate_limit_{$action}_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'))['count'] ?? 0);
        
        header("X-RateLimit-Limit: {$config['requests']}");
        header("X-RateLimit-Window: {$config['window']}");
        header("X-RateLimit-Remaining: " . max(0, $remaining));
    }
    
    // =========================================================================
    // Rate Limiting par rôle (employé, administrateur, etc.)
    // =========================================================================
    
    /**
     * Obtenir la limite pour une action + rôle donné
     * Si le rôle a une surcharge définie, elle est utilisée ; sinon fallback sur la limite standard
     */
    private static function getLimitByRole(string $action, string $role): array {
        self::loadConfig();
        
        // Vérifier si une surcharge existe pour ce rôle + action
        if (isset(self::$configByRole[$role][$action])) {
            return self::$configByRole[$role][$action];
        }
        
        // Sinon fallback sur la limite standard
        return self::getLimit($action);
    }
    
    /**
     * Vérifier la limite en tenant compte du rôle de l'utilisateur
     * La clé de stockage inclut le rôle pour séparer les compteurs
     */
    public static function checkLimitByRole(string $action, string $role, ?string $ip = null): bool {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $config = self::getLimitByRole($action, $role);
        
        $key = "rate_limit_{$action}_{$role}_{$ip}";
        $current = self::getStorage($key);
        
        if ($current === null) {
            self::setStorage($key, ['count' => 1, 'start' => time()]);
            return true;
        }
        
        // Vérifier si la fenêtre est expirée
        if (time() - $current['start'] > $config['window']) {
            self::setStorage($key, ['count' => 1, 'start' => time()]);
            return true;
        }
        
        // Vérifier si la limite est dépassée
        if ($current['count'] >= $config['requests']) {
            $remainingTime = $config['window'] - (time() - $current['start']);
            error_log("Rate limit exceeded for {$action} (role: {$role}) from {$ip}. Try again in {$remainingTime}s");
            return false;
        }
        
        // Incrémenter le compteur
        $current['count']++;
        self::setStorage($key, $current);
        return true;
    }
    
    /**
     * Obtenir le temps d'attente restant pour un rôle donné
     */
    public static function getWaitTimeByRole(string $action, string $role, ?string $ip = null): int {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $config = self::getLimitByRole($action, $role);
        
        $key = "rate_limit_{$action}_{$role}_{$ip}";
        $current = self::getStorage($key);
        
        if ($current === null) return 0;
        
        $elapsed = time() - $current['start'];
        return max(0, $config['window'] - $elapsed);
    }
    
    /**
     * Headers HTTP pour rate limiting par rôle
     */
    public static function setRateLimitHeadersByRole(string $action, string $role): void {
        $config = self::getLimitByRole($action, $role);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$action}_{$role}_{$ip}";
        $remaining = $config['requests'] - (self::getStorage($key)['count'] ?? 0);
        
        header("X-RateLimit-Limit: {$config['requests']}");
        header("X-RateLimit-Window: {$config['window']}");
        header("X-RateLimit-Remaining: " . max(0, $remaining));
    }
}
