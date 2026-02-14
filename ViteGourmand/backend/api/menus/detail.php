<?php
// backend/api/menus/detail.php
// Détail d'un menu avec ses plats associés
// Accessible publiquement
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer l'identifiant (id ou menu_key)
$menuId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$menuKey = isset($_GET['key']) ? trim($_GET['key']) : '';

if ($menuId <= 0 && empty($menuKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètre id ou key requis']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Récupérer le menu
    if ($menuId > 0) {
        $stmt = $db->prepare("SELECT * FROM menus WHERE id = ?");
        $stmt->execute([$menuId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM menus WHERE menu_key = ?");
        $stmt->execute([$menuKey]);
    }

    $menu = $stmt->fetch();

    if (!$menu) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Menu introuvable']);
        exit();
    }

    // Typage
    $menu['id'] = (int) $menu['id'];
    $menu['prix_par_personne'] = (float) $menu['prix_par_personne'];
    $menu['nombre_personnes_min'] = (int) $menu['nombre_personnes_min'];
    $menu['stock_disponible'] = (int) $menu['stock_disponible'];
    $menu['actif'] = (bool) $menu['actif'];

    // Récupérer les plats associés
    $stmtPlats = $db->prepare("
        SELECT p.id, p.nom, p.type, p.allergenes
        FROM plats p
        JOIN menu_plats mp ON mp.plat_id = p.id
        WHERE mp.menu_id = ?
        ORDER BY FIELD(p.type, 'entree', 'plat', 'dessert'), p.nom
    ");
    $stmtPlats->execute([$menu['id']]);
    $plats = $stmtPlats->fetchAll();

    // Organiser les plats par type
    $sections = ['entrees' => [], 'plats' => [], 'desserts' => []];
    $allergenes = [];

    foreach ($plats as &$plat) {
        $plat['id'] = (int) $plat['id'];
        $platAllergenes = json_decode($plat['allergenes'], true) ?? [];
        $plat['allergenes'] = $platAllergenes;

        switch ($plat['type']) {
            case 'entree':
                $sections['entrees'][] = $plat;
                break;
            case 'plat':
                $sections['plats'][] = $plat;
                break;
            case 'dessert':
                $sections['desserts'][] = $plat;
                break;
        }

        foreach ($platAllergenes as $a) {
            if (!in_array($a, $allergenes)) {
                $allergenes[] = $a;
            }
        }
    }

    $menu['plats'] = $plats;
    $menu['sections'] = $sections;
    $menu['allergenes'] = $allergenes;

    echo json_encode([
        'success' => true,
        'data' => $menu
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/detail.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération du menu'
    ]);
}
