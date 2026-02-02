<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Afficher les erreurs directement dans la réponse
function handleException($e) {
    echo "<h2>Erreur :</h2>";
    echo "<p><strong>Message :</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier :</strong> " . $e->getFile() . " (ligne " . $e->getLine() . ")</p>";
    echo "<h3>Trace :</h3><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}

set_exception_handler('handleException');

// Inclure la classe Database
require_once __DIR__ . '/classes/Database.php';

echo "<h1>Test de connexion à la base de données</h1>";

try {
    // 1. Tester la connexion
    echo "<h2>1. Connexion à la base de données...</h2>";
    $db = Database::getInstance()->getConnection();
    echo "<p style='color:green;'>✓ Connexion réussie !</p>";

    // 2. Tester une requête simple
    echo "<h2>2. Test d'une requête simple...</h2>";
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "<p>Résultat de la requête : ";
    print_r($result);
    echo "</p>";

    // 3. Vérifier si la table users existe
    echo "<h2>3. Vérification de la table 'users'...</h2>";
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green;'>✓ La table 'users' existe.</p>";
        
        // 4. Compter les utilisateurs
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "<p>Nombre d'utilisateurs dans la table : " . $result['count'] . "</p>";
        
        // 5. Afficher la structure de la table
        echo "<h2>4. Structure de la table 'users' :</h2>";
        $stmt = $db->query("DESCRIBE users");
        echo "<table border='1' cellpadding='5'><tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Valeur par défaut</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>✗ La table 'users' n'existe pas.</p>";
        echo "<p>Créez la table avec la commande SQL suivante :</p>";
        echo "<pre style='background:#f5f5f5;padding:10px;'>";
        echo htmlspecialchars("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT NOT NULL,
            role ENUM('utilisateur', 'employe', 'administrateur') DEFAULT 'utilisateur',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "</pre>";
    }

    // 6. Tester l'insertion (optionnel)
    if (isset($_GET['test_insert']) && $db->query("SHOW TABLES LIKE 'users'")->rowCount() > 0) {
        echo "<h2>5. Test d'insertion...</h2>";
        try {
            $testEmail = 'test_' . time() . '@example.com';
            $passwordHash = password_hash('Test123!', PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, phone, address)
                VALUES (?, ?, 'Test', 'User', '0612345678', '123 Test Street')
            ");
            $stmt->execute([$testEmail, $passwordHash]);
            
            $lastId = $db->lastInsertId();
            echo "<p style='color:green;'>✓ Insertion réussie ! ID : " . $lastId . "</p>";
            
            // Nettoyer (optionnel)
            $db->exec("DELETE FROM users WHERE id = " . $lastId);
            
        } catch (PDOException $e) {
            echo "<p style='color:red;'>✗ Erreur lors de l'insertion : " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p>Ajoutez <code>?test_insert=1</code> à l'URL pour tester l'insertion.</p>";
    }

} catch (Exception $e) {
    // Gestion des erreurs
    handleException($e);
}

echo "<h2>Informations système :</h2>";
echo "<ul>";
echo "<li>PHP version: " . phpversion() . "</li>";
echo "<li>PDO disponible : " . (extension_loaded('pdo') ? 'Oui' : 'Non') . "</li>";
echo "<li>PDO MySQL disponible : " . (extension_loaded('pdo_mysql') ? 'Oui' : 'Non') . "</li>";
echo "<li>Fuseau horaire : " . date_default_timezone_get() . "</li>";
echo "<li>Heure actuelle : " . date('Y-m-d H:i:s') . "</li>";
echo "</ul>";