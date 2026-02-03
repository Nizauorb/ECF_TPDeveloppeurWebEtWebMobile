<?php
// backend/api/contact/send.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration des headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
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

// Inclure les dépendances
require_once __DIR__ . '/../../classes/EmailService.php';

try {
    // Lire les données JSON
    $data = json_decode(file_get_contents('php://input'), true);

    // Validation des champs
    if (!$data || !isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tous les champs sont requis (nom, email, message)'
        ]);
        exit();
    }

    // Validation et nettoyage des données
    $name = trim($data['name']);
    $email = trim($data['email']);
    $message = trim($data['message']);

    // Validation du nom
    if (empty($name) || strlen($name) < 2) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom doit contenir au moins 2 caractères'
        ]);
        exit();
    }

    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'adresse email est invalide'
        ]);
        exit();
    }

    // Validation du message
    if (empty($message) || strlen($message) < 10) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le message doit contenir au moins 10 caractères'
        ]);
        exit();
    }

    // Limiter la longueur du message
    if (strlen($message) > 2000) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le message ne peut pas dépasser 2000 caractères'
        ]);
        exit();
    }

    // Envoyer l'email avec EmailService
    $emailService = new EmailService();
    $emailService->sendContactEmail($name, $email, $message);

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
