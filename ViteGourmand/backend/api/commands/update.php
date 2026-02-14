<?php
// backend/api/commands/update.php
// Modification d'une commande (sauf le menu) — uniquement si statut "en_attente"
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';
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

// Validation de la taille et du type de contenu
if (!InputValidator::validateInputSize()) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Les données envoyées sont trop volumineuses']);
    exit();
}

if (!InputValidator::validateContentType()) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Type de contenu non autorisé']);
    exit();
}

// Protection CSRF
CSRFProtection::requireValidation();

// Rate Limiting
RateLimiter::setRateLimitHeaders('create_order');

if (!RateLimiter::checkLimit('create_order')) {
    $waitTime = RateLimiter::getWaitTime('create_order');
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validation JWT : extraire le user_id du token
$jwtPayload = JWTHelper::getFromRequest();
$userId = ($jwtPayload && isset($jwtPayload['user_id'])) ? (int) $jwtPayload['user_id'] : (isset($data['user_id']) ? (int) $data['user_id'] : 0);

$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de commande invalide']);
    exit();
}

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non identifié (JWT invalide ou absent)']);
    exit();
}

// Champs modifiables (tout sauf le menu)
$requiredFields = [
    'nombre_personnes', 'adresse_livraison', 'code_postal_livraison',
    'ville_livraison', 'date_prestation', 'heure_prestation'
];

foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Champ manquant : {$field}"]);
        exit();
    }
}

$nbPersonnes = intval($data['nombre_personnes']);
$distanceKm = isset($data['distance_km']) ? floatval($data['distance_km']) : null;

// Validation de la date (doit être dans le futur)
$datePrestation = $data['date_prestation'];
if (strtotime($datePrestation) <= strtotime('today')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La date de prestation doit être dans le futur']);
    exit();
}

// Sanitization des chaînes
$adresseLivraison = htmlspecialchars(trim($data['adresse_livraison']), ENT_QUOTES, 'UTF-8');
$codePostal = htmlspecialchars(trim($data['code_postal_livraison']), ENT_QUOTES, 'UTF-8');
$villeLivraison = htmlspecialchars(trim($data['ville_livraison']), ENT_QUOTES, 'UTF-8');
$heurePrestation = htmlspecialchars(trim($data['heure_prestation']), ENT_QUOTES, 'UTF-8');
$notes = isset($data['notes']) ? htmlspecialchars(trim($data['notes']), ENT_QUOTES, 'UTF-8') : null;

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que la commande existe, appartient à l'utilisateur, et est en_attente
    $stmt = $db->prepare("SELECT id, statut, nombre_personnes_min, prix_unitaire FROM commandes WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $commande = $stmt->fetch();

    if (!$commande) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit();
    }

    if ($commande['statut'] !== 'en_attente') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cette commande ne peut plus être modifiée (statut : ' . $commande['statut'] . ')']);
        exit();
    }

    // Vérifier le nombre minimum de personnes
    $nbPersonnesMin = (int) $commande['nombre_personnes_min'];
    if ($nbPersonnes < $nbPersonnesMin) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Le nombre minimum de personnes pour ce menu est {$nbPersonnesMin}"]);
        exit();
    }

    // --- RECALCUL SERVEUR (ne jamais faire confiance au client) ---
    $prixUnitaire = (float) $commande['prix_unitaire'];
    $sousTotal = $prixUnitaire * $nbPersonnes;

    // Réduction : 10% si nb >= min + 5
    $reductionPourcent = 0;
    $reductionMontant = 0;
    if ($nbPersonnes >= $nbPersonnesMin + 5) {
        $reductionPourcent = 10;
        $reductionMontant = $sousTotal * 0.1;
    }

    // Frais de livraison : 5€ base + 0.59€/km si hors Bordeaux
    $fraisLivraisonBase = 5.0;
    $fraisKm = 0.59;
    $fraisLivraison = $fraisLivraisonBase;
    if ($distanceKm !== null && $distanceKm > 0) {
        $fraisLivraison = round($fraisLivraisonBase + $distanceKm * $fraisKm, 2);
    }

    $total = $sousTotal - $reductionMontant + $fraisLivraison;

    // Mettre à jour la commande (tout sauf menu_key et menu_nom)
    $stmtUpdate = $db->prepare("
        UPDATE commandes SET
            nombre_personnes = ?,
            adresse_livraison = ?,
            code_postal_livraison = ?,
            ville_livraison = ?,
            date_prestation = ?,
            heure_prestation = ?,
            frais_livraison = ?,
            distance_km = ?,
            sous_total = ?,
            reduction_pourcent = ?,
            reduction_montant = ?,
            total = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ");

    $stmtUpdate->execute([
        $nbPersonnes,
        $adresseLivraison,
        $codePostal,
        $villeLivraison,
        $datePrestation,
        $heurePrestation,
        $fraisLivraison,
        $distanceKm,
        $sousTotal,
        $reductionPourcent,
        $reductionMontant,
        $total,
        $notes,
        $orderId,
        $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Commande modifiée avec succès',
        'data' => [
            'frais_livraison' => $fraisLivraison,
            'sous_total' => $sousTotal,
            'reduction_pourcent' => $reductionPourcent,
            'reduction_montant' => $reductionMontant,
            'total' => $total
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur modification commande: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification de la commande']);
}
