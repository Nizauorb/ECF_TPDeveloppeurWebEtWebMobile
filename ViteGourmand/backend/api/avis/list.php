<?php
// backend/api/avis/list.php
// Liste les avis validés (accessible publiquement pour la page d'accueil)
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

    // Récupérer uniquement les avis validés avec les infos client (prénom uniquement pour la confidentialité)
    $sql = "SELECT a.id, a.note, a.commentaire, a.created_at,
                   u.prenom AS client_prenom,
                   c.menu_nom
            FROM avis a
            JOIN users u ON a.user_id = u.id
            JOIN commandes c ON a.commande_id = c.id
            WHERE a.valide = 1
            ORDER BY a.created_at DESC";

    // Limite optionnelle
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
    if ($limit > 0) {
        $sql .= " LIMIT " . $limit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater
    foreach ($avis as &$a) {
        $a['note'] = (int) $a['note'];
        $a['created_at'] = date('d/m/Y', strtotime($a['created_at']));
    }
    unset($a);

    echo json_encode([
        'success' => true,
        'data' => $avis,
        'count' => count($avis)
    ]);

} catch (PDOException $e) {
    error_log("Erreur chargement avis: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des avis']);
}
