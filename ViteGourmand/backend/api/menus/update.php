<?php
// backend/api/menus/update.php
// Modifier un menu existant (réservé employé/administrateur)
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

    // Vérifier le rôle
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role IN ('employe', 'administrateur')");
    $stmtRole->execute([$operatorId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle insuffisant']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // ID du menu à modifier
    $menuId = isset($data['id']) ? (int) $data['id'] : 0;
    if ($menuId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du menu requis']);
        exit();
    }

    // Vérifier que le menu existe
    $stmtCheck = $db->prepare("SELECT id FROM menus WHERE id = ?");
    $stmtCheck->execute([$menuId]);
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Menu introuvable']);
        exit();
    }

    // Construction dynamique de la requête UPDATE
    $fields = [];
    $params = [];

    if (isset($data['titre']) && trim($data['titre']) !== '') {
        $fields[] = "titre = ?";
        $params[] = htmlspecialchars(trim($data['titre']), ENT_QUOTES, 'UTF-8');
    }

    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $params[] = htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8');
    }

    if (isset($data['image'])) {
        $fields[] = "image = ?";
        $params[] = htmlspecialchars(trim($data['image']), ENT_QUOTES, 'UTF-8');
    }

    if (isset($data['theme'])) {
        $allowedThemes = ['Classique', 'Noel', 'Paques', 'Event'];
        if (!in_array($data['theme'], $allowedThemes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Thème invalide']);
            exit();
        }
        $fields[] = "theme = ?";
        $params[] = $data['theme'];
    }

    if (isset($data['regime'])) {
        $allowedRegimes = ['Classique', 'Végétarien', 'Vegan', 'Halal'];
        if (!in_array($data['regime'], $allowedRegimes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Régime invalide']);
            exit();
        }
        $fields[] = "regime = ?";
        $params[] = $data['regime'];
    }

    if (isset($data['prix_par_personne'])) {
        $prix = floatval($data['prix_par_personne']);
        if ($prix <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le prix doit être supérieur à 0']);
            exit();
        }
        $fields[] = "prix_par_personne = ?";
        $params[] = $prix;
    }

    if (isset($data['nombre_personnes_min'])) {
        $nbMin = intval($data['nombre_personnes_min']);
        if ($nbMin < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le nombre minimum de personnes doit être au moins 1']);
            exit();
        }
        $fields[] = "nombre_personnes_min = ?";
        $params[] = $nbMin;
    }

    if (isset($data['stock_disponible'])) {
        $fields[] = "stock_disponible = ?";
        $params[] = intval($data['stock_disponible']);
    }

    if (isset($data['conditions_commande'])) {
        $fields[] = "conditions_commande = ?";
        $params[] = htmlspecialchars(trim($data['conditions_commande']), ENT_QUOTES, 'UTF-8');
    }

    if (isset($data['actif'])) {
        $fields[] = "actif = ?";
        $params[] = (int)(bool)$data['actif'];
    }

    $db->beginTransaction();

    // Mettre à jour les champs du menu si nécessaire
    if (!empty($fields)) {
        $params[] = $menuId;
        $sql = "UPDATE menus SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmtUpdate = $db->prepare($sql);
        $stmtUpdate->execute($params);
    }

    // Mettre à jour les associations plats si fourni
    if (isset($data['plat_ids']) && is_array($data['plat_ids'])) {
        // Supprimer les anciennes associations
        $stmtDel = $db->prepare("DELETE FROM menu_plats WHERE menu_id = ?");
        $stmtDel->execute([$menuId]);

        // Insérer les nouvelles
        $stmtAssoc = $db->prepare("INSERT INTO menu_plats (menu_id, plat_id) VALUES (?, ?)");
        foreach ($data['plat_ids'] as $platId) {
            $platId = (int) $platId;
            if ($platId > 0) {
                $stmtAssoc->execute([$menuId, $platId]);
            }
        }
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Menu mis à jour avec succès'
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur menus/update.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la mise à jour du menu'
    ]);
}
