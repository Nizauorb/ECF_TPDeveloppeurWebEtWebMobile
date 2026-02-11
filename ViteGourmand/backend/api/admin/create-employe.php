<?php
// backend/api/admin/create-employe.php
// Créer un compte employé (réservé administrateur)
// Génère un mot de passe aléatoire et envoie un email au nouvel employé
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/SecurityHeaders.php';
require_once __DIR__ . '/../../classes/JWTHelper.php';
require_once __DIR__ . '/../../classes/CSRFProtection.php';
require_once __DIR__ . '/../../classes/InputValidator.php';
require_once __DIR__ . '/../../classes/Mailer.php';

// Headers de sécurité
SecurityHeaders::setSecureCORS();
SecurityHeaders::setErrorHeaders();

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    SecurityHeaders::setOptionsHeaders();
    http_response_code(200);
    exit();
}

// Vérifier la méthode HTTP
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

// Validation JWT
$jwtPayload = JWTHelper::getFromRequest();
if (!$jwtPayload || !isset($jwtPayload['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentification requise']);
    exit();
}

$adminId = (int) $jwtPayload['user_id'];

try {
    $db = Database::getInstance()->getConnection();

    // Vérifier que l'utilisateur est administrateur
    $stmtRole = $db->prepare("SELECT role FROM users WHERE id = ? AND role = 'administrateur'");
    $stmtRole->execute([$adminId]);
    if (!$stmtRole->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : rôle administrateur requis']);
        exit();
    }

    // Lire les données JSON
    $data = json_decode(file_get_contents('php://input'), true);

    // Validation des champs requis
    $requiredFields = ['lastName', 'firstName', 'email', 'phone'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Champs manquants : ' . implode(', ', $missingFields)
        ]);
        exit();
    }

    // Validation de l'email
    $emailValidation = InputValidator::validateEmail($data['email']);
    if (!$emailValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $emailValidation['error']]);
        exit();
    }

    // Validation du nom et prénom
    $lastNameValidation = InputValidator::validateName($data['lastName']);
    if (!$lastNameValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nom : ' . $lastNameValidation['error']]);
        exit();
    }

    $firstNameValidation = InputValidator::validateName($data['firstName']);
    if (!$firstNameValidation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Prénom : ' . $firstNameValidation['error']]);
        exit();
    }

    // Validation du numéro de téléphone
    if (!preg_match('/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/', $data['phone'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format de numéro de téléphone invalide (format français attendu)'
        ]);
        exit();
    }

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$emailValidation['sanitized']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cette adresse email est déjà utilisée'
        ]);
        exit();
    }

    // Générer un mot de passe aléatoire sécurisé (12 caractères)
    $generatedPassword = generateSecurePassword(12);

    // Hachage du mot de passe
    $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

    // Insertion du nouvel employé
    $stmt = $db->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, phone, role)
        VALUES (:email, :password_hash, :first_name, :last_name, :phone, 'employe')
    ");

    $stmt->execute([
        ':email' => $emailValidation['sanitized'],
        ':password_hash' => $passwordHash,
        ':first_name' => $firstNameValidation['sanitized'],
        ':last_name' => $lastNameValidation['sanitized'],
        ':phone' => $data['phone']
    ]);

    $employeId = $db->lastInsertId();

    // Envoyer l'email avec les identifiants
    $htmlBody = buildWelcomeEmail(
        $firstNameValidation['sanitized'],
        $lastNameValidation['sanitized'],
        $emailValidation['sanitized'],
        $generatedPassword
    );

    try {
        Mailer::send(
            $emailValidation['sanitized'],
            'Vite&Gourmand — Votre compte employé a été créé',
            $htmlBody
        );
        $emailSent = true;
    } catch (Exception $mailError) {
        error_log("Erreur envoi mail create-employe: " . $mailError->getMessage());
        $emailSent = false;
    }

    // Réponse de succès
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Compte employé créé avec succès',
        'data' => [
            'employe' => [
                'id' => (int) $employeId,
                'email' => $emailValidation['sanitized'],
                'firstName' => $firstNameValidation['sanitized'],
                'lastName' => $lastNameValidation['sanitized'],
                'phone' => $data['phone']
            ],
            'emailSent' => $emailSent,
            'generatedPassword' => $generatedPassword
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur create-employe.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création du compte employé'
    ]);
}

// ============================================
// Fonctions utilitaires
// ============================================

/**
 * Génère un mot de passe sécurisé aléatoire
 */
function generateSecurePassword(int $length = 12): string {
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $special = '!@#$%&*?';

    // Garantir au moins un caractère de chaque type
    $password = $lower[random_int(0, strlen($lower) - 1)]
        . $upper[random_int(0, strlen($upper) - 1)]
        . $digits[random_int(0, strlen($digits) - 1)]
        . $special[random_int(0, strlen($special) - 1)];

    // Compléter avec des caractères aléatoires
    $allChars = $lower . $upper . $digits . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    // Mélanger le mot de passe
    return str_shuffle($password);
}

/**
 * Construit le corps HTML de l'email de bienvenue employé
 */
function buildWelcomeEmail(string $firstName, string $lastName, string $email, string $password): string {
    return '
    <!DOCTYPE html>
    <html lang="fr">
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
        <div style="background-color: #ffffff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="text-align: center; margin-bottom: 20px;">
                <h1 style="color: #627D4A; margin: 0;">Vite&Gourmand</h1>
                <p style="color: #6c757d; margin-top: 5px;">Espace Employé</p>
            </div>
            <hr style="border: none; border-top: 2px solid #627D4A; margin: 20px 0;">
            <h2 style="color: #333;">Bienvenue ' . htmlspecialchars($firstName) . ' !</h2>
            <p style="color: #555; line-height: 1.6;">
                Votre compte employé a été créé par l\'administrateur. Voici vos identifiants de connexion :
            </p>
            <div style="background-color: #f0f4ec; border-left: 4px solid #627D4A; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0;">
                <p style="margin: 5px 0;"><strong>Email :</strong> ' . htmlspecialchars($email) . '</p>
                <p style="margin: 5px 0;"><strong>Mot de passe :</strong> <code style="background: #fff; padding: 2px 8px; border-radius: 3px; font-size: 14px;">' . htmlspecialchars($password) . '</code></p>
            </div>
            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0;">
                <p style="margin: 0; color: #856404;">
                    <strong>Important :</strong> Nous vous recommandons de changer votre mot de passe dès votre première connexion.
                </p>
            </div>
            <p style="color: #555; line-height: 1.6;">
                Vous pouvez vous connecter à votre espace employé pour gérer les commandes, les menus, les horaires et les avis clients.
            </p>
            <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">
            <p style="color: #999; font-size: 12px; text-align: center;">
                Cet email a été envoyé automatiquement. Merci de ne pas y répondre.
            </p>
        </div>
    </body>
    </html>';
}
