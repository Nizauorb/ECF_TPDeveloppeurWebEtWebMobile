<?php
// backend/api/horaires/update.php
// Modifier les horaires d'un jour (réservé employé/administrateur)
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

    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de l\'horaire requis']);
        exit();
    }

    $horaireId = (int) $data['id'];

    // Vérifier que l'horaire existe
    $stmtCheck = $db->prepare("SELECT id, jour FROM horaires WHERE id = ?");
    $stmtCheck->execute([$horaireId]);
    $horaire = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$horaire) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Horaire non trouvé']);
        exit();
    }

    // Construire la requête de mise à jour dynamiquement
    $allowedFields = ['ouvert', 'matin_ouverture', 'matin_fermeture', 'soir_ouverture', 'soir_fermeture'];
    $updates = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            if ($field === 'ouvert') {
                $updates[] = "$field = ?";
                $params[] = $data[$field] ? 1 : 0;
            } else {
                // Champs horaires : accepter null (pour vider un créneau) ou une heure valide
                if ($data[$field] === null || $data[$field] === '') {
                    $updates[] = "$field = NULL";
                } else {
                    // Valider le format HH:MM ou HH:MM:SS
                    $time = trim($data[$field]);
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => "Format d'heure invalide pour {$field} (attendu HH:MM)"]);
                        exit();
                    }
                    // Normaliser en HH:MM:SS
                    if (strlen($time) === 5) $time .= ':00';
                    $updates[] = "$field = ?";
                    $params[] = $time;
                }
            }
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucun champ à mettre à jour']);
        exit();
    }

    // Validation logique : vérifier la cohérence des horaires
    // Récupérer les valeurs finales (existantes + nouvelles)
    $stmtCurrent = $db->prepare("SELECT * FROM horaires WHERE id = ?");
    $stmtCurrent->execute([$horaireId]);
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    $finalOuvert = array_key_exists('ouvert', $data) ? ($data['ouvert'] ? 1 : 0) : $current['ouvert'];
    
    if ($finalOuvert) {
        $finalMatinOuv = array_key_exists('matin_ouverture', $data) ? $data['matin_ouverture'] : $current['matin_ouverture'];
        $finalMatinFerm = array_key_exists('matin_fermeture', $data) ? $data['matin_fermeture'] : $current['matin_fermeture'];
        $finalSoirOuv = array_key_exists('soir_ouverture', $data) ? $data['soir_ouverture'] : $current['soir_ouverture'];
        $finalSoirFerm = array_key_exists('soir_fermeture', $data) ? $data['soir_fermeture'] : $current['soir_fermeture'];

        // Si ouvert, au moins un créneau doit être renseigné
        $hasMatinSlot = !empty($finalMatinOuv) && !empty($finalMatinFerm);
        $hasSoirSlot = !empty($finalSoirOuv) && !empty($finalSoirFerm);

        if (!$hasMatinSlot && !$hasSoirSlot) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Un jour ouvert doit avoir au moins un créneau horaire (matin ou soir)']);
            exit();
        }

        // Vérifier que l'heure d'ouverture est avant l'heure de fermeture
        if ($hasMatinSlot && $finalMatinOuv >= $finalMatinFerm) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'L\'heure d\'ouverture du matin doit être avant l\'heure de fermeture']);
            exit();
        }
        // Pour le soir, on autorise la fermeture après minuit (ex: 18:30 → 00:30)
        // Donc on ne vérifie que si les deux heures sont du même côté de minuit
        if ($hasSoirSlot && $finalSoirOuv >= $finalSoirFerm) {
            // Si la fermeture est après minuit (00:00 - 05:00), c'est valide
            $fermNormalized = substr($finalSoirFerm, 0, 5);
            if ($fermNormalized > '05:00') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'L\'heure d\'ouverture du soir doit être avant l\'heure de fermeture (fermeture après minuit autorisée jusqu\'à 05:00)']);
                exit();
            }
        }
    }

    // Exécuter la mise à jour
    $sql = "UPDATE horaires SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $horaireId;

    $stmtUpdate = $db->prepare($sql);
    $stmtUpdate->execute($params);

    // Retourner l'horaire mis à jour
    $stmtResult = $db->prepare("SELECT id, jour, jour_order, ouvert, matin_ouverture, matin_fermeture, soir_ouverture, soir_fermeture, updated_at FROM horaires WHERE id = ?");
    $stmtResult->execute([$horaireId]);
    $updated = $stmtResult->fetch(PDO::FETCH_ASSOC);

    // Formater
    $updated['ouvert'] = (bool) $updated['ouvert'];
    $updated['matin_ouverture'] = $updated['matin_ouverture'] ? substr($updated['matin_ouverture'], 0, 5) : null;
    $updated['matin_fermeture'] = $updated['matin_fermeture'] ? substr($updated['matin_fermeture'], 0, 5) : null;
    $updated['soir_ouverture'] = $updated['soir_ouverture'] ? substr($updated['soir_ouverture'], 0, 5) : null;
    $updated['soir_fermeture'] = $updated['soir_fermeture'] ? substr($updated['soir_fermeture'], 0, 5) : null;

    echo json_encode([
        'success' => true,
        'message' => "Horaires du {$horaire['jour']} mis à jour avec succès",
        'data' => $updated
    ]);

} catch (PDOException $e) {
    error_log("Erreur mise à jour horaire: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour des horaires']);
}
