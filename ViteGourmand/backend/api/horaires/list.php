<?php
// backend/api/horaires/list.php
// Liste les horaires d'ouverture du restaurant
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

    $stmt = $db->prepare("SELECT id, jour, jour_order, ouvert, matin_ouverture, matin_fermeture, soir_ouverture, soir_fermeture, updated_at FROM horaires ORDER BY jour_order ASC");
    $stmt->execute();
    $horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater les heures (retirer les secondes pour l'affichage)
    foreach ($horaires as &$h) {
        $h['ouvert'] = (bool) $h['ouvert'];
        $h['matin_ouverture'] = $h['matin_ouverture'] ? substr($h['matin_ouverture'], 0, 5) : null;
        $h['matin_fermeture'] = $h['matin_fermeture'] ? substr($h['matin_fermeture'], 0, 5) : null;
        $h['soir_ouverture'] = $h['soir_ouverture'] ? substr($h['soir_ouverture'], 0, 5) : null;
        $h['soir_fermeture'] = $h['soir_fermeture'] ? substr($h['soir_fermeture'], 0, 5) : null;
    }
    unset($h);

    echo json_encode([
        'success' => true,
        'data' => $horaires,
        'count' => count($horaires)
    ]);

} catch (PDOException $e) {
    error_log("Erreur chargement horaires: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des horaires']);
}
