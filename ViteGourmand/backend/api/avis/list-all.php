<?php
// backend/api/avis/list-all.php
// Liste tous les avis (réservé employé/administrateur) pour la modération
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

    // Vérifier le rôle
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role IN ('employe', 'administrateur')");
    $stmtRole->execute([$operatorId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle insuffisant']);
        exit();
    }

    // Filtre optionnel par statut de validation
    $filterValide = isset($_GET['valide']) && $_GET['valide'] !== '' && is_numeric($_GET['valide']) ? $_GET['valide'] : null;

    $sql = "SELECT a.id, a.user_id, a.commande_id, a.note, a.commentaire, a.valide,
                   a.validated_by, a.validated_at, a.created_at,
                   u.first_name AS client_prenom, u.last_name AS client_nom, u.email AS client_email,
                   c.menu_nom, c.date_prestation,
                   v.first_name AS validator_prenom, v.last_name AS validator_nom
            FROM avis a
            JOIN users u ON a.user_id = u.id
            JOIN commandes c ON a.commande_id = c.id
            LEFT JOIN users v ON a.validated_by = v.id";

    $params = [];
    if ($filterValide !== null && $filterValide !== '') {
        $sql .= " WHERE a.valide = ?";
        $params[] = (int) $filterValide;
    }

    $sql .= " ORDER BY a.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater
    foreach ($avis as &$a) {
        $a['note'] = (int) $a['note'];
        $a['valide'] = (int) $a['valide'];
    }
    unset($a);

    // Stats rapides
    $stmtStats = $db->prepare("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN valide = 0 THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN valide = 1 THEN 1 ELSE 0 END) AS valides,
        SUM(CASE WHEN valide = 2 THEN 1 ELSE 0 END) AS refuses
        FROM avis");
    $stmtStats->execute();
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $avis,
        'count' => count($avis),
        'stats' => [
            'total' => (int) $stats['total'],
            'en_attente' => (int) $stats['en_attente'],
            'valides' => (int) $stats['valides'],
            'refuses' => (int) $stats['refuses']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur chargement avis (admin): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des avis']);
}
