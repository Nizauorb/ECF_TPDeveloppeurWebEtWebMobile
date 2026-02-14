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

    // Récupérer les commandes de l'utilisateur avec l'avis éventuel
    $stmt = $db->prepare("
        SELECT c.id, c.user_id, c.client_nom, c.client_prenom, c.client_email, c.client_telephone,
               c.menu_key, c.menu_nom, c.prix_unitaire, c.nombre_personnes, c.nombre_personnes_min,
               c.adresse_livraison, c.ville_livraison, c.code_postal_livraison,
               c.date_prestation, c.heure_prestation, c.frais_livraison, c.distance_km,
               c.sous_total, c.reduction_pourcent, c.reduction_montant, c.total,
               c.notes, c.statut, c.motif_annulation, c.mode_contact_annulation,
               c.date_commande, c.updated_at,
               a.id AS avis_id, a.note AS avis_note, a.commentaire AS avis_commentaire, a.valide AS avis_valide, a.created_at AS avis_date
        FROM commandes c
        LEFT JOIN avis a ON a.commande_id = c.id
        WHERE c.user_id = ?
        ORDER BY c.date_commande DESC
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
        $command['menu_nom'] = html_entity_decode($command['menu_nom'], ENT_QUOTES, 'UTF-8');
        // Formater l'avis s'il existe
        if ($command['avis_id']) {
            $command['avis'] = [
                'id' => (int) $command['avis_id'],
                'note' => (int) $command['avis_note'],
                'commentaire' => $command['avis_commentaire'],
                'valide' => (int) $command['avis_valide'],
                'date' => $command['avis_date']
            ];
        } else {
            $command['avis'] = null;
        }
        unset($command['avis_id'], $command['avis_note'], $command['avis_commentaire'], $command['avis_valide'], $command['avis_date']);
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
