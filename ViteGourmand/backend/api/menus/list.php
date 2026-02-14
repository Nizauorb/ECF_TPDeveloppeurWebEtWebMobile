<?php
// backend/api/menus/list.php
// Liste tous les menus actifs avec leurs plats associés
// Accessible publiquement (visiteurs, utilisateurs, employés, admins)
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

try {
    $db = Database::getInstance()->getConnection();

    // Paramètre optionnel : inclure les menus inactifs (pour employé/admin)
    $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';

    // Récupérer les menus
    $sql = "SELECT * FROM menus";
    if (!$includeInactive) {
        $sql .= " WHERE actif = 1";
    }
    $sql .= " ORDER BY id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $menus = $stmt->fetchAll();

    // Pour chaque menu, récupérer ses plats associés
    $stmtPlats = $db->prepare("
        SELECT p.id, p.nom, p.type, p.allergenes
        FROM plats p
        JOIN menu_plats mp ON mp.plat_id = p.id
        WHERE mp.menu_id = ?
        ORDER BY FIELD(p.type, 'entree', 'plat', 'dessert'), p.nom
    ");

    foreach ($menus as &$menu) {
        $menu['id'] = (int) $menu['id'];
        $menu['prix_par_personne'] = (float) $menu['prix_par_personne'];
        $menu['nombre_personnes_min'] = (int) $menu['nombre_personnes_min'];
        $menu['stock_disponible'] = (int) $menu['stock_disponible'];
        $menu['actif'] = (bool) $menu['actif'];

        // Décoder les entités HTML dans les champs texte
        $menu['titre'] = html_entity_decode($menu['titre'], ENT_QUOTES, 'UTF-8');
        $menu['description'] = html_entity_decode($menu['description'], ENT_QUOTES, 'UTF-8');
        $menu['conditions_commande'] = html_entity_decode($menu['conditions_commande'], ENT_QUOTES, 'UTF-8');
        $menu['regime'] = html_entity_decode($menu['regime'], ENT_QUOTES, 'UTF-8');

        // Récupérer les plats
        $stmtPlats->execute([$menu['id']]);
        $plats = $stmtPlats->fetchAll();

        // Organiser les plats par type
        $sections = ['entrees' => [], 'plats' => [], 'desserts' => []];
        $allergenes = [];

        foreach ($plats as &$plat) {
            $plat['id'] = (int) $plat['id'];
            $plat['nom'] = html_entity_decode($plat['nom'], ENT_QUOTES, 'UTF-8');
            $platAllergenes = json_decode($plat['allergenes'], true) ?? [];
            $plat['allergenes'] = $platAllergenes;

            // Ajouter aux sections
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

            // Agréger les allergènes du menu
            foreach ($platAllergenes as $a) {
                if (!in_array($a, $allergenes)) {
                    $allergenes[] = $a;
                }
            }
        }

        $menu['plats'] = $plats;
        $menu['sections'] = $sections;
        $menu['allergenes'] = $allergenes;
    }

    echo json_encode([
        'success' => true,
        'data' => $menus,
        'count' => count($menus)
    ]);

} catch (PDOException $e) {
    error_log("Erreur menus/list.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des menus'
    ]);
}
