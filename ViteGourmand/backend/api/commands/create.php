<?php
// backend/api/commands/create.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/RateLimiter.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';
require_once __DIR__ . '/../../classes/Mailer.php';
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

$data = json_decode(file_get_contents('php://input'), true);

// Validation JWT : extraire le user_id et le rôle du token si disponible
$jwtPayload = JWTHelper::getFromRequest();
$userId = ($jwtPayload && isset($jwtPayload['user_id'])) ? (int) $jwtPayload['user_id'] : intval($data['user_id'] ?? 0);
$userRole = ($jwtPayload && isset($jwtPayload['role'])) ? $jwtPayload['role'] : 'utilisateur';

// Interdire aux employés et admins de passer des commandes
if (in_array($userRole, ['employe', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Les employés et administrateurs ne peuvent pas passer de commandes.']);
    exit();
}

// Rate Limiting par rôle (employés/admins ont des limites plus souples)
RateLimiter::setRateLimitHeadersByRole('create_order', $userRole);

if (!RateLimiter::checkLimitByRole('create_order', $userRole)) {
    $waitTime = RateLimiter::getWaitTimeByRole('create_order', $userRole);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Veuillez réessayer dans ' . ceil($waitTime / 60) . ' minute(s).',
        'retry_after' => $waitTime
    ]);
    exit();
}

// Validation des champs obligatoires
$requiredFields = [
    'user_id', 'menu_key', 'menu_nom', 'prix_unitaire',
    'nombre_personnes', 'nombre_personnes_min',
    'adresse_livraison', 'code_postal_livraison', 'ville_livraison',
    'date_prestation', 'heure_prestation',
    'frais_livraison', 'sous_total', 'total'
];

foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Champ manquant : {$field}"]);
        exit();
    }
}
$nbPersonnes = intval($data['nombre_personnes']);
$nbPersonnesMin = intval($data['nombre_personnes_min']);
$prixUnitaire = floatval($data['prix_unitaire']);
$fraisLivraison = floatval($data['frais_livraison']);
$sousTotal = floatval($data['sous_total']);
$reductionPourcent = floatval($data['reduction_pourcent'] ?? 0);
$reductionMontant = floatval($data['reduction_montant'] ?? 0);
$total = floatval($data['total']);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
    exit();
}

if ($nbPersonnes < $nbPersonnesMin) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Le nombre minimum de personnes pour ce menu est {$nbPersonnesMin}"]);
    exit();
}

// Validation de la date (doit être dans le futur)
$datePrestation = $data['date_prestation'];
if (strtotime($datePrestation) <= strtotime('today')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La date de prestation doit être dans le futur']);
    exit();
}

// Sanitization des chaînes
$menuKey = htmlspecialchars(trim($data['menu_key']), ENT_QUOTES, 'UTF-8');
$menuNom = trim($data['menu_nom']);
$adresseLivraison = htmlspecialchars(trim($data['adresse_livraison']), ENT_QUOTES, 'UTF-8');
$codePostal = htmlspecialchars(trim($data['code_postal_livraison']), ENT_QUOTES, 'UTF-8');
$villeLivraison = htmlspecialchars(trim($data['ville_livraison']), ENT_QUOTES, 'UTF-8');
$heurePrestation = htmlspecialchars(trim($data['heure_prestation']), ENT_QUOTES, 'UTF-8');
$notes = isset($data['notes']) ? htmlspecialchars(trim($data['notes']), ENT_QUOTES, 'UTF-8') : null;
$distanceKm = isset($data['distance_km']) ? floatval($data['distance_km']) : null;

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier le stock disponible pour le menu
    $menuName = $data['menu_key'];
    $stmtCheck = $db->prepare("SELECT stock_disponible FROM menus WHERE menu_key = ?");
    $stmtCheck->execute([$menuName]);
    $menu = $stmtCheck->fetch();

    if (!$menu) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Menu non trouvé']);
        exit();
    }

    if ($menu['stock_disponible'] < $nbPersonnes) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Stock insuffisant pour ce nombre de personnes']);
        exit();
    }

    // Vérifier que l'utilisateur existe
    $stmtUser = $db->prepare("SELECT id, last_name, first_name, email, phone FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit();
    }

    // Insérer la commande
    $stmt = $db->prepare("
        INSERT INTO commandes (
            user_id, client_nom, client_prenom, client_email, client_telephone,
            menu_key, menu_nom, prix_unitaire, nombre_personnes, nombre_personnes_min,
            adresse_livraison, ville_livraison, code_postal_livraison,
            date_prestation, heure_prestation, frais_livraison, distance_km,
            sous_total, reduction_pourcent, reduction_montant, total,
            notes, statut
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 'en_attente'
        )
    ");

    $stmt->execute([
        $userId, $user['last_name'], $user['first_name'], $user['email'], $user['phone'],
        $menuKey, $menuNom, $prixUnitaire, $nbPersonnes, $nbPersonnesMin,
        $adresseLivraison, $villeLivraison, $codePostal,
        $datePrestation, $heurePrestation, $fraisLivraison, $distanceKm,
        $sousTotal, $reductionPourcent, $reductionMontant, $total,
        $notes
    ]);

    $orderId = $db->lastInsertId();

    // Envoyer le mail de confirmation
    try {
        $emailBody = generateConfirmationEmail($orderId, $user, $menuNom, $nbPersonnes, $prixUnitaire, $sousTotal, $reductionPourcent, $reductionMontant, $fraisLivraison, $total, $adresseLivraison, $codePostal, $villeLivraison, $datePrestation, $heurePrestation, $notes);
        Mailer::send($user['email'], "Confirmation de commande #{$orderId} - Vite&Gourmand", $emailBody);
        error_log("Email de confirmation envoyé pour la commande #{$orderId}");
    } catch (Exception $emailError) {
        error_log("Erreur envoi email confirmation commande #{$orderId}: " . $emailError->getMessage());
        // On ne bloque pas la commande si le mail échoue
    }

    echo json_encode([
        'success' => true,
        'message' => 'Commande créée avec succès',
        'order_id' => (int) $orderId
    ]);

} catch (PDOException $e) {
    error_log("Erreur création commande: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la commande']);
}

/**
 * Générer le HTML de l'email de confirmation de commande
 */
function generateConfirmationEmail($orderId, $user, $menuNom, $nbPersonnes, $prixUnitaire, $sousTotal, $reductionPourcent, $reductionMontant, $fraisLivraison, $total, $adresse, $codePostal, $ville, $date, $heure, $notes) {
    $dateFormatted = date('d/m/Y', strtotime($date));
    $prixUnitaireFormatted = number_format($prixUnitaire, 2, ',', ' ');
    $sousTotalFormatted = number_format($sousTotal, 2, ',', ' ');
    $fraisFormatted = number_format($fraisLivraison, 2, ',', ' ');
    $totalFormatted = number_format($total, 2, ',', ' ');
    $reductionFormatted = number_format($reductionMontant, 2, ',', ' ');

    $reductionHtml = '';
    if ($reductionPourcent > 0) {
        $reductionHtml = "
            <tr>
                <td style='padding: 8px 0; color: #28a745;'>Réduction ({$reductionPourcent}%)</td>
                <td style='padding: 8px 0; text-align: right; color: #28a745; font-weight: 600;'>- {$reductionFormatted} €</td>
            </tr>";
    }

    $notesHtml = '';
    if ($notes) {
        $notesHtml = "
            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                <p style='margin: 0; font-weight: 600; color: #856404;'>Notes :</p>
                <p style='margin: 5px 0 0; color: #856404;'>{$notes}</p>
            </div>";
    }

    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #2E2E2E; line-height: 1.6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden;'>
            <div style='background: linear-gradient(135deg, #627D4A, #4a5f38); padding: 40px 30px; text-align: center; color: white;'>
                <div style='font-size: 2.5rem; margin-bottom: 10px;'>✅</div>
                <div style='font-size: 1.5rem; font-weight: 700; margin-bottom: 5px;'>Vite&Gourmand</div>
                <h1 style='font-size: 1.5rem; font-weight: 700; margin: 0;'>Commande confirmée</h1>
                <p style='margin: 10px 0 0; opacity: 0.9;'>Commande #{$orderId}</p>
            </div>
            <div style='padding: 40px 30px;'>
                <h2 style='color: #627D4A; font-size: 1.25rem; margin-bottom: 20px;'>Bonjour {$user['first_name']},</h2>
                <p>Votre commande a été enregistrée avec succès. Voici le récapitulatif :</p>

                <div style='background: rgba(98,125,74,0.05); border-left: 4px solid #627D4A; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px; color: #627D4A;'>{$menuNom}</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6c757d;'>Prix unitaire</td>
                            <td style='padding: 8px 0; text-align: right;'>{$prixUnitaireFormatted} €/pers.</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6c757d;'>Nombre de personnes</td>
                            <td style='padding: 8px 0; text-align: right;'>× {$nbPersonnes}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6c757d;'>Sous-total</td>
                            <td style='padding: 8px 0; text-align: right;'>{$sousTotalFormatted} €</td>
                        </tr>
                        {$reductionHtml}
                        <tr>
                            <td style='padding: 8px 0; color: #6c757d;'>Frais de livraison</td>
                            <td style='padding: 8px 0; text-align: right;'>{$fraisFormatted} €</td>
                        </tr>
                        <tr style='border-top: 2px solid #627D4A;'>
                            <td style='padding: 12px 0; font-weight: 700; font-size: 1.1rem;'>Total</td>
                            <td style='padding: 12px 0; text-align: right; font-weight: 700; font-size: 1.1rem; color: #627D4A;'>{$totalFormatted} €</td>
                        </tr>
                    </table>
                </div>

                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px; font-size: 1rem; color: #627D4A;'>📍 Livraison</h3>
                    <p style='margin: 5px 0;'><strong>Adresse :</strong> {$adresse}, {$codePostal} {$ville}</p>
                    <p style='margin: 5px 0;'><strong>Date :</strong> {$dateFormatted}</p>
                    <p style='margin: 5px 0;'><strong>Heure :</strong> {$heure}</p>
                </div>

                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px; font-size: 1rem; color: #627D4A;'>👤 Client</h3>
                    <p style='margin: 5px 0;'><strong>Nom :</strong> {$user['first_name']} {$user['last_name']}</p>
                    <p style='margin: 5px 0;'><strong>Email :</strong> {$user['email']}</p>
                    <p style='margin: 5px 0;'><strong>Téléphone :</strong> {$user['phone']}</p>
                </div>

                {$notesHtml}

                <p style='margin-top: 20px; color: #6c757d;'>Votre commande est en attente de validation par notre équipe. Vous recevrez une notification dès qu'elle sera acceptée.</p>
            </div>
            <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                <p style='margin: 0; font-size: 0.85rem; color: #6c757d;'>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                <p style='margin: 5px 0 0; font-size: 0.85rem; color: #6c757d;'>&copy; 2026 Vite&Gourmand - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>";
}
