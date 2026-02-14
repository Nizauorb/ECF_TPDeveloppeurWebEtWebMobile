<?php
// backend/config/config.php

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1']);

if ($isLocal) {
    // =========================================================================
    // ENVIRONNEMENT LOCAL (XAMPP)
    // =========================================================================
    return [
        'db' => [
            'host' => 'localhost',
            'dbname' => 'vite_gourmand_local',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'mail' => [
            'host' => 'localhost',
            'port' => 1025,
            'smtp_auth' => false,
            'smtp_secure' => '',
            'username' => '',
            'password' => '',
            'charset' => 'UTF-8',
            'from_email' => 'noreply@vitegourmand.com',
            'from_name' => 'Vite & Gourmand',
            'admin_email' => 'contact@vitegourmand.com',
            'base_url' => 'http://localhost:3000'
        ],
        'jwt' => [
            'secret' => '<ta-clè-secrète-pour-jwt>',
            'expires_in' => '24h'
        ]
    ];
} else {
    // =========================================================================
    // ENVIRONNEMENT PRODUCTION (OVH)
    // =========================================================================
    return [
        'db' => [
            'host' => '',
            'dbname' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'mail' => [
            'host' => 'smtp.resend.com',
            'port' => 587,
            'smtp_auth' => true,
            'smtp_secure' => 'tls',
            'username' => '',
            'password' => '',
            'resend_api_key' => '',
            'charset' => 'UTF-8',
            'from_email' => '',
            'from_name' => 'Vite & Gourmand',
            'admin_email' => '',
            'base_url' => ''
        ],
        'jwt' => [
            'secret' => '<ta-clè-secrète-pour-jwt>',
            'expires_in' => '24h'
        ]
    ];
}