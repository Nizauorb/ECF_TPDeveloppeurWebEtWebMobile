<?php
// backend/api/menus/create.php
// Créer un nouveau menu (réservé employé/administrateur)
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    // Validation des champs obligatoires
    $requiredFields = ['menu_key', 'titre', 'theme', 'prix_par_personne', 'nombre_personnes_min'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Champ manquant : {$field}"]);
            exit();
        }
    }

    // Validation des valeurs
    $allowedThemes = ['Classique', 'Noel', 'Paques', 'Event'];
    if (!in_array($data['theme'], $allowedThemes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Thème invalide']);
        exit();
    }

    $allowedRegimes = ['Classique', 'Végétarien', 'Vegan', 'Halal'];
    $regime = $data['regime'] ?? 'Classique';
    if (!in_array($regime, $allowedRegimes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Régime invalide']);
        exit();
    }

    $prix = floatval($data['prix_par_personne']);
    if ($prix <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le prix doit être supérieur à 0']);
        exit();
    }

    $nbMin = intval($data['nombre_personnes_min']);
    if ($nbMin < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le nombre minimum de personnes doit être au moins 1']);
        exit();
    }

    // Vérifier l'unicité du menu_key
    $menuKey = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($data['menu_key'])));
    $stmtCheck = $db->prepare("SELECT id FROM menus WHERE menu_key = ?");
    $stmtCheck->execute([$menuKey]);
    if ($stmtCheck->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un menu avec cette clé existe déjà']);
        exit();
    }

    // Sanitization
    $titre = htmlspecialchars(trim($data['titre']), ENT_QUOTES, 'UTF-8');
    $description = isset($data['description']) ? htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8') : null;
    $image = isset($data['image']) ? htmlspecialchars(trim($data['image']), ENT_QUOTES, 'UTF-8') : null;
    $conditionsCommande = isset($data['conditions_commande']) ? htmlspecialchars(trim($data['conditions_commande']), ENT_QUOTES, 'UTF-8') : null;
    $stockDisponible = isset($data['stock_disponible']) ? intval($data['stock_disponible']) : 10;
    $actif = isset($data['actif']) ? (int)(bool)$data['actif'] : 1;

    // Insertion
    $db->beginTransaction();

    $stmtInsert = $db->prepare("
        INSERT INTO menus (menu_key, titre, description, image, theme, regime, prix_par_personne, nombre_personnes_min, stock_disponible, conditions_commande, actif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $menuKey, $titre, $description, $image, $data['theme'], $regime,
        $prix, $nbMin, $stockDisponible, $conditionsCommande, $actif
    ]);

    $menuId = (int) $db->lastInsertId();

    // Associer les plats si fournis
    if (isset($data['plat_ids']) && is_array($data['plat_ids'])) {
        $stmtAssoc = $db->prepare("INSERT INTO menu_plats (menu_id, plat_id) VALUES (?, ?)");
        foreach ($data['plat_ids'] as $platId) {
            $platId = (int) $platId;
            if ($platId > 0) {
                $stmtAssoc->execute([$menuId, $platId]);
            }
        }
    }

    $db->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Menu créé avec succès',
        'data' => ['id' => $menuId, 'menu_key' => $menuKey]
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erreur menus/create.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création du menu'
    ]);
}
