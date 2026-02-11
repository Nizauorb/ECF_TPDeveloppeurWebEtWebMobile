<?php
// backend/api/commands/all-commands.php
// Liste toutes les commandes (réservé employé/administrateur)
// Supporte les filtres : statut, recherche client, date début/fin
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
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Validation JWT
$jwtPayload = JWTHelper::getFromRequest();
if (!$jwtPayload || !isset($jwtPayload['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise']);
    exit();
}

$operatorId = (int) $jwtPayload['user_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'opérateur est employé ou administrateur
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role IN ('employe', 'administrateur')");
    $stmtRole->execute([$operatorId]);
    $operator = $stmtRole->fetch();

    if (!$operator) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle insuffisant']);
        exit();
    }

    // Construction dynamique de la requête avec filtres
    $where = [];
    $params = [];

    // Filtre par statut
    $statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
    if (!empty($statut)) {
        $allowedStatuses = [
            'en_attente', 'acceptee', 'en_preparation', 'en_livraison',
            'livree', 'attente_retour_materiel', 'terminee', 'annulee'
        ];
        if (in_array($statut, $allowedStatuses)) {
            $where[] = "c.statut = ?";
            $params[] = $statut;
        }
    }

    // Filtre par recherche client (nom, prénom ou email)
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if (!empty($search)) {
        $where[] = "(c.client_nom LIKE ? OR c.client_prenom LIKE ? OR c.client_email LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Filtre par date de prestation (début)
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = "c.date_prestation >= ?";
        $params[] = $dateFrom;
    }

    // Filtre par date de prestation (fin)
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = "c.date_prestation <= ?";
        $params[] = $dateTo;
    }

    // Construire la requête
    $sql = "
        SELECT c.id, c.user_id, c.client_nom, c.client_prenom, c.client_email, c.client_telephone,
               c.menu_key, c.menu_nom, c.prix_unitaire, c.nombre_personnes, c.nombre_personnes_min,
               c.adresse_livraison, c.ville_livraison, c.code_postal_livraison,
               c.date_prestation, c.heure_prestation, c.frais_livraison, c.distance_km,
               c.sous_total, c.reduction_pourcent, c.reduction_montant, c.total,
               c.notes, c.statut, c.motif_annulation, c.mode_contact_annulation,
               c.date_commande, c.updated_at
        FROM commandes c
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY c.date_commande ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
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
    }

    echo json_encode([
        'success' => true,
        'data' => $commands,
        'count' => count($commands)
    ]);

} catch (PDOException $e) {
    error_log("Erreur all-commands.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des commandes'
    ]);
}
