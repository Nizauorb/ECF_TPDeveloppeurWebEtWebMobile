<?php
// backend/classes/InputValidator.php

class InputValidator {
    
    /**
     * Valider et assainir un nom
     */
    public static function validateName(string $name): array {
        $name = trim($name);
        
        // Validation basique
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Le nom est requis', 'sanitized' => ''];
        }
        
        if (strlen($name) < 2) {
            return ['valid' => false, 'error' => 'Le nom doit contenir au moins 2 caractères', 'sanitized' => ''];
        }
        
        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Le nom ne peut pas dépasser 100 caractères', 'sanitized' => ''];
        }
        
        // Assainissement contre XSS
        $sanitized = self::sanitizeString($name);
        
        // Vérification après assainissement
        if (empty($sanitized)) {
            return ['valid' => false, 'error' => 'Le nom contient des caractères invalides', 'sanitized' => ''];
        }
        
        // Vérification des caractères autorisés (lettres, espaces, tirets, apostrophes)
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\'\.]{2,100}$/', $sanitized)) {
            return ['valid' => false, 'error' => 'Le nom contient des caractères non autorisés', 'sanitized' => ''];
        }
        
        return ['valid' => true, 'error' => '', 'sanitized' => $sanitized];
    }
    
    /**
     * Valider et assainir un email
     */
    public static function validateEmail(string $email): array {
        $email = trim($email);
        
        // Validation basique
        if (empty($email)) {
            return ['valid' => false, 'error' => 'L\'email est requis', 'sanitized' => ''];
        }
        
        if (strlen($email) > 254) {
            return ['valid' => false, 'error' => 'L\'email est trop long', 'sanitized' => ''];
        }
        
        // Validation format email avec filter_var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'L\'adresse email est invalide', 'sanitized' => ''];
        }
        
        // Assainissement
        $sanitized = strtolower(trim($email));
        
        // Vérifications supplémentaires de sécurité
        if (self::containsSuspiciousPatterns($sanitized)) {
            return ['valid' => false, 'error' => 'L\'email contient des éléments suspects', 'sanitized' => ''];
        }
        
        return ['valid' => true, 'error' => '', 'sanitized' => $sanitized];
    }
    
    /**
     * Valider et assainir un message
     */
    public static function validateMessage(string $message): array {
        $message = trim($message);
        
        // Validation basique
        if (empty($message)) {
            return ['valid' => false, 'error' => 'Le message est requis', 'sanitized' => ''];
        }
        
        if (strlen($message) < 10) {
            return ['valid' => false, 'error' => 'Le message doit contenir au moins 10 caractères', 'sanitized' => ''];
        }
        
        if (strlen($message) > 2000) {
            return ['valid' => false, 'error' => 'Le message ne peut pas dépasser 2000 caractères', 'sanitized' => ''];
        }
        
        // Assainissement contre XSS et injections
        $sanitized = self::sanitizeString($message);
        
        // Vérification après assainissement
        if (strlen($sanitized) < 10) {
            return ['valid' => false, 'error' => 'Le message contient trop de caractères invalides', 'sanitized' => ''];
        }
        
        // Détection de contenu suspect
        if (self::containsSuspiciousContent($sanitized)) {
            error_log("Message suspect bloqué: " . substr($sanitized, 0, 100));
            return ['valid' => false, 'error' => 'Le message contient du contenu non autorisé', 'sanitized' => ''];
        }
        
        return ['valid' => true, 'error' => '', 'sanitized' => $sanitized];
    }
    
    /**
     * Assainir une chaîne de caractères
     */
    private static function sanitizeString(string $input): string {
        // Supprimer les tags HTML/PHP
        $clean = strip_tags($input);
        
        // Convertir les entités HTML
        $clean = htmlspecialchars($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Supprimer les caractères de contrôle sauf newline et tab
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        
        // Normaliser les espaces multiples
        $clean = preg_replace('/\s+/', ' ', $clean);
        
        return trim($clean);
    }
    
    /**
     * Vérifier les patterns suspects dans les emails
     */
    private static function containsSuspiciousPatterns(string $email): bool {
        $suspicious = [
            '/\+.*@/',  // Email avec + (souvent utilisé pour spam)
            '/test.*@/',  // Emails de test
            '/admin.*@/',  // Emails admin suspects
            '/.*\.ru$/',  // Domaines .ru (souvent spam)
            '/.*\.cn$/',  // Domaines .cn (souvent spam)
        ];
        
        foreach ($suspicious as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Vérifier le contenu suspect dans les messages
     */
    private static function containsSuspiciousContent(string $message): bool {
        $suspicious = [
            // Patterns d'injection SQL
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            
            // Patterns XSS
            '/<script/i',
            '/javascript:/i',
            '/onload=/i',
            '/onerror=/i',
            
            // Patterns spam
            '/click\s+here/i',
            '/buy\s+now/i',
            '/limited\s+offer/i',
            '/act\s+now/i',
            '/free\s+money/i',
            
            // Patterns malveillants
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
        ];
        
        foreach ($suspicious as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Valider la taille des données entrantes
     */
    public static function validateInputSize(): bool {
        $contentLength = (int)$_SERVER['CONTENT_LENGTH'] ?? 0;
        $maxSize = 1 * 1024 * 1024; // 1MB
        
        if ($contentLength > $maxSize) {
            error_log("Tentative d'envoi de données trop volumineuses: {$contentLength} bytes");
            return false;
        }
        
        return true;
    }
    
    /**
     * Valider le type de contenu
     */
    public static function validateContentType(): bool {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        // Accepter uniquement application/json
        if (!str_contains($contentType, 'application/json')) {
            error_log("Content-Type non autorisé: {$contentType}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Validation complète des données de contact
     */
    public static function validateContactData(array $data): array {
        $errors = [];
        $sanitized = [];
        
        // Valider le nom
        $nameResult = self::validateName($data['name'] ?? '');
        if (!$nameResult['valid']) {
            $errors['name'] = $nameResult['error'];
        } else {
            $sanitized['name'] = $nameResult['sanitized'];
        }
        
        // Valider l'email
        $emailResult = self::validateEmail($data['email'] ?? '');
        if (!$emailResult['valid']) {
            $errors['email'] = $emailResult['error'];
        } else {
            $sanitized['email'] = $emailResult['sanitized'];
        }
        
        // Valider le message
        $messageResult = self::validateMessage($data['message'] ?? '');
        if (!$messageResult['valid']) {
            $errors['message'] = $messageResult['error'];
        } else {
            $sanitized['message'] = $messageResult['sanitized'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized' => $sanitized
        ];
    }
}
