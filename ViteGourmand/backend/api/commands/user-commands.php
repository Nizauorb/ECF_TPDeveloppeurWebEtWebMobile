<?php
// backend/api/commands/user-commands.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/JWTHelper.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
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

// Validation du token JWT
$jwtPayload = JWTHelper::getFromRequest();
if ($jwtPayload && isset($jwtPayload['user_id'])) {
    $userId = (int) $jwtPayload['user_id'];
} else {
    // Fallback sur le paramètre GET (rétrocompatibilité tokens existants)
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
}

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
        SELECT id, user_id, client_nom, client_prenom, client_email, client_telephone,
               menu_key, menu_nom, prix_unitaire, nombre_personnes, nombre_personnes_min,
               adresse_livraison, ville_livraison, code_postal_livraison,
               date_prestation, heure_prestation, frais_livraison, distance_km,
               sous_total, reduction_pourcent, reduction_montant, total,
               notes, statut, motif_annulation, mode_contact_annulation,
               date_commande, updated_at
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
        $command['nombre_personnes'] = (int) $command['nombre_personnes'];
        $command['nombre_personnes_min'] = (int) $command['nombre_personnes_min'];
        $command['prix_unitaire'] = (float) $command['prix_unitaire'];
        $command['frais_livraison'] = (float) $command['frais_livraison'];
        $command['sous_total'] = (float) $command['sous_total'];
        $command['reduction_pourcent'] = (float) $command['reduction_pourcent'];
        $command['reduction_montant'] = (float) $command['reduction_montant'];
        $command['total'] = (float) $command['total'];
        if ($command['distance_km'] !== null) $command['distance_km'] = (float) $command['distance_km'];
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
        'error' => 'Erreur interne'
    ]);
}
