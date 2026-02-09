<?php
// backend/api/commands/update-status.php
// Mise à jour du statut d'une commande (employé/admin)
// Envoie un mail de demande d'avis quand le statut passe à "terminée"
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

// Validation JWT : vérifier que c'est un employé ou admin
$jwtPayload = JWTHelper::getFromRequest();
if (!$jwtPayload || !isset($jwtPayload['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise']);
    exit();
}

$operatorId = (int) $jwtPayload['user_id'];
$orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$newStatus = isset($data['statut']) ? trim($data['statut']) : '';
$motif = isset($data['motif']) ? htmlspecialchars(trim($data['motif']), ENT_QUOTES, 'UTF-8') : null;

// Statuts autorisés
$allowedStatuses = [
    'acceptee', 'en_preparation', 'en_livraison', 'livree',
    'attente_retour_materiel', 'terminee', 'annulee'
];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides (order_id ou statut)']);
    exit();
}

// Si annulation par employé, un motif est obligatoire
if ($newStatus === 'annulee' && empty($motif)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Un motif est obligatoire pour annuler une commande']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'opérateur est employé ou admin
    $stmtOp = $db->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('employe', 'admin')");
    $stmtOp->execute([$operatorId]);
    $operator = $stmtOp->fetch();

    if (!$operator) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle insuffisant']);
        exit();
    }

    // Récupérer la commande
    $stmt = $db->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$orderId]);
    $commande = $stmt->fetch();

    if (!$commande) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit();
    }

    // Mettre à jour le statut
    $stmtUpdate = $db->prepare("UPDATE commandes SET statut = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpdate->execute([$newStatus, $orderId]);

    // Si le statut passe à "terminée", envoyer un mail de demande d'avis
    if ($newStatus === 'terminee') {
        try {
            $emailBody = generateReviewRequestEmail(
                $commande['id'],
                $commande['client_prenom'],
                $commande['client_nom'],
                $commande['menu_nom'],
                $commande['date_prestation']
            );
            Mailer::send(
                $commande['client_email'],
                "Votre avis compte ! Commande #{$commande['id']} - Vite&Gourmand",
                $emailBody
            );
            error_log("Email demande d'avis envoyé pour la commande #{$commande['id']}");
        } catch (Exception $emailError) {
            error_log("Erreur envoi email avis commande #{$commande['id']}: " . $emailError->getMessage());
        }
    }

    // Si le statut passe à "attente_retour_materiel", envoyer un mail d'avertissement
    if ($newStatus === 'attente_retour_materiel') {
        try {
            $emailBody = generateMaterialReturnEmail(
                $commande['id'],
                $commande['client_prenom'],
                $commande['client_nom']
            );
            Mailer::send(
                $commande['client_email'],
                "Retour de matériel - Commande #{$commande['id']} - Vite&Gourmand",
                $emailBody
            );
            error_log("Email retour matériel envoyé pour la commande #{$commande['id']}");
        } catch (Exception $emailError) {
            error_log("Erreur envoi email retour matériel commande #{$commande['id']}: " . $emailError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Statut mis à jour avec succès'
    ]);

} catch (PDOException $e) {
    error_log("Erreur mise à jour statut commande: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut']);
}

/**
 * Générer le HTML de l'email de demande d'avis
 */
function generateReviewRequestEmail($orderId, $prenom, $nom, $menuNom, $datePrestation) {
    $dateFormatted = date('d/m/Y', strtotime($datePrestation));
    // Lien vers la page d'avis (à adapter selon le routing frontend)
    $reviewLink = "https://vite-gourmand.maxime-brouazin.fr/UserDashboard?section=review&order={$orderId}";

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
                <div style='font-size: 2.5rem; margin-bottom: 10px;'>⭐</div>
                <div style='font-size: 1.5rem; font-weight: 700; margin-bottom: 5px;'>Vite&Gourmand</div>
                <h1 style='font-size: 1.5rem; font-weight: 700; margin: 0;'>Votre avis nous intéresse !</h1>
            </div>
            <div style='padding: 40px 30px;'>
                <h2 style='color: #627D4A; font-size: 1.25rem; margin-bottom: 20px;'>Bonjour {$prenom},</h2>
                <p>Votre commande <strong>#{$orderId}</strong> ({$menuNom}) du <strong>{$dateFormatted}</strong> est maintenant terminée.</p>
                <p>Nous espérons que vous avez apprécié votre expérience ! Votre avis est précieux et nous aide à nous améliorer.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='font-size: 2rem; margin-bottom: 15px;'>⭐⭐⭐⭐⭐</div>
                    <p style='color: #6c757d; margin-bottom: 20px;'>Notez votre expérience de 1 à 5 étoiles et laissez-nous un commentaire.</p>
                    <a href='{$reviewLink}' style='display: inline-block; background: linear-gradient(135deg, #627D4A, #4a5f38); color: white; text-decoration: none; padding: 15px 40px; border-radius: 8px; font-weight: 600; font-size: 1.1rem;'>
                        Laisser mon avis
                    </a>
                </div>
                
                <p style='margin-top: 20px; color: #6c757d; font-size: 0.9rem;'>Merci pour votre confiance et à bientôt chez Vite&Gourmand !</p>
            </div>
            <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                <p style='margin: 0; font-size: 0.85rem; color: #6c757d;'>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                <p style='margin: 5px 0 0; font-size: 0.85rem; color: #6c757d;'>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Générer le HTML de l'email de retour de matériel
 * Cahier des charges : si du matériel a été prêté, le client a 10 jours ouvrés pour le restituer, sinon 600€ de frais
 */
function generateMaterialReturnEmail($orderId, $prenom, $nom) {
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; color: #2E2E2E; line-height: 1.6; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden;'>
            <div style='background: linear-gradient(135deg, #dc3545, #c82333); padding: 40px 30px; text-align: center; color: white;'>
                <div style='font-size: 2.5rem; margin-bottom: 10px;'>📦</div>
                <div style='font-size: 1.5rem; font-weight: 700; margin-bottom: 5px;'>Vite&Gourmand</div>
                <h1 style='font-size: 1.5rem; font-weight: 700; margin: 0;'>Retour de matériel requis</h1>
            </div>
            <div style='padding: 40px 30px;'>
                <h2 style='color: #dc3545; font-size: 1.25rem; margin-bottom: 20px;'>Bonjour {$prenom},</h2>
                <p>Suite à votre commande <strong>#{$orderId}</strong>, du matériel vous a été prêté.</p>
                
                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 0; font-weight: 600; color: #856404;'>⚠️ Important</p>
                    <p style='margin: 10px 0 0; color: #856404;'>
                        Conformément à nos conditions générales de vente, vous disposez de <strong>10 jours ouvrés</strong> 
                        à compter de ce jour pour restituer le matériel prêté.
                    </p>
                    <p style='margin: 10px 0 0; color: #856404;'>
                        Passé ce délai, des <strong>frais de 600,00 €</strong> vous seront facturés.
                    </p>
                </div>
                
                <p>Pour organiser le retour du matériel, veuillez nous contacter :</p>
                <ul style='color: #6c757d;'>
                    <li>Par email : <a href='mailto:contact@vite-gourmand.fr'>contact@vite-gourmand.fr</a></li>
                    <li>Par téléphone : 05 56 00 00 00</li>
                </ul>
                
                <p style='margin-top: 20px; color: #6c757d;'>Merci de votre compréhension.</p>
            </div>
            <div style='background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;'>
                <p style='margin: 0; font-size: 0.85rem; color: #6c757d;'>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
                <p style='margin: 5px 0 0; font-size: 0.85rem; color: #6c757d;'>&copy; 2024 Vite&Gourmand - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>";
}
