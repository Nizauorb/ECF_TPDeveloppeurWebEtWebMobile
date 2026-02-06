<?php
// backend/api/commands/user-commands.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../classes/Database.php';

// Configuration des headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit();
}

// TODO: Implémenter une vraie validation de token JWT
// Pour l'instant, on se fie au user_id passé en paramètre
// (le token n'est pas encore stocké/validé côté serveur)

// Récupérer l'ID utilisateur depuis les paramètres
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID utilisateur invalide'
    ]);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Récupérer les commandes de l'utilisateur
    $stmt = $db->prepare("
        SELECT id, user_id, menu_nom, quantite, total, statut, notes, date_commande, updated_at
        FROM commandes
        WHERE user_id = ?
        ORDER BY date_commande DESC
    ");
    $stmt->execute([$userId]);
    $commands = $stmt->fetchAll();

    // Convertir les types numériques
    foreach ($commands as &$command) {
        $command['id'] = (int) $command['id'];
        $command['user_id'] = (int) $command['user_id'];
        $command['quantite'] = (int) $command['quantite'];
        $command['total'] = (float) $command['total'];
    }

    echo json_encode([
        'success' => true,
        'data' => $commands
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des commandes',
        'error' => $e->getMessage()
    ]);
}
