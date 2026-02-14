<?php
// backend/api/admin/stats-commandes.php
// Statistiques des commandes par menu (réservé administrateur)
// Retourne le nombre de commandes et le CA par menu, avec filtres de période optionnels
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

$adminId = (int) $jwtPayload['user_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'utilisateur est administrateur
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role = 'administrateur'");
    $stmtRole->execute([$adminId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle administrateur requis']);
        exit();
    }

    // Filtres de période optionnels
    $where = [];
    $params = [];

    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = "c.date_commande >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = "c.date_commande <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }

    // Exclure les commandes annulées des stats
    $where[] = "c.statut != 'annulee'";

    $whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

    // Stats par menu : nombre de commandes + CA
    $sql = "
        SELECT 
            c.menu_nom,
            COUNT(*) as nb_commandes,
            SUM(c.total) as chiffre_affaires
        FROM commandes c
        {$whereClause}
        GROUP BY c.menu_nom
        ORDER BY nb_commandes DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $statsParMenu = $stmt->fetchAll();

    // Convertir les types
    $totalCommandes = 0;
    $totalCA = 0;
    foreach ($statsParMenu as &$stat) {
        $stat['nb_commandes'] = (int) $stat['nb_commandes'];
        $stat['chiffre_affaires'] = (float) $stat['chiffre_affaires'];
        $stat['menu_nom'] = html_entity_decode($stat['menu_nom'], ENT_QUOTES, 'UTF-8');
        $totalCommandes += $stat['nb_commandes'];
        $totalCA += $stat['chiffre_affaires'];
    }

    // Stats globales
    $sqlGlobal = "
        SELECT 
            COUNT(*) as total_commandes,
            COALESCE(SUM(c.total), 0) as total_ca,
            COALESCE(AVG(c.total), 0) as panier_moyen
        FROM commandes c
        {$whereClause}
    ";
    $stmtGlobal = $db->prepare($sqlGlobal);
    $stmtGlobal->execute($params);
    $statsGlobal = $stmtGlobal->fetch();

    echo json_encode([
        'success' => true,
        'data' => [
            'par_menu' => $statsParMenu,
            'global' => [
                'total_commandes' => (int) $statsGlobal['total_commandes'],
                'total_ca' => (float) $statsGlobal['total_ca'],
                'panier_moyen' => round((float) $statsGlobal['panier_moyen'], 2)
            ]
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur stats-commandes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des statistiques'
    ]);
}
