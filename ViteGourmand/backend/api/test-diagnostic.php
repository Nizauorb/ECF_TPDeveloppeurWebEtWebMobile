<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$results = [];

// 1. Version PHP
$results['php_version'] = phpversion();

// 2. Extensions requises
$results['extensions'] = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
];

// 3. Test connexion BDD (directe, sans classe Database)
try {
    $cfg = require __DIR__ . '/../config/config.php';
    $dbCfg = $cfg['db'];
    $port = $dbCfg['port'] ?? 3306;
    $dsn = "mysql:host={$dbCfg['host']};port={$port};dbname={$dbCfg['dbname']};charset={$dbCfg['charset']}";
    $results['dsn'] = $dsn;
    $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password']);
    $results['database'] = 'OK - Connexion reussie';
} catch (PDOException $e) {
    $results['database'] = 'ERREUR PDO - ' . $e->getMessage();
} catch (Exception $e) {
    $results['database'] = 'ERREUR - ' . $e->getMessage();
}

// 4. Test fichiers classes
$classes = [
    'Database.php',
    'SecurityHeaders.php',
    'RateLimiter.php',
    'CSRFProtection.php',
    'InputValidator.php',
];
foreach ($classes as $class) {
    $path = __DIR__ . '/../classes/' . $class;
    $results['files'][$class] = file_exists($path) ? 'OK' : 'MANQUANT';
}

// 5. Test vendor (PHPMailer)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$results['vendor_autoload'] = file_exists($vendorAutoload) ? 'OK' : 'MANQUANT';

// 6. Test config
try {
    $config = require __DIR__ . '/../config/config.php';
    $results['config'] = 'OK - Config chargee';
    $results['config_db_host'] = $config['db']['host'] ?? 'non defini';
    $results['config_is_prod'] = (strpos($config['db']['host'], 'localhost') === false) ? 'OUI (prod)' : 'NON (local)';
} catch (Exception $e) {
    $results['config'] = 'ERREUR - ' . $e->getMessage();
}

// 7. Test ecriture sessions
$results['session_save_path'] = session_save_path() ?: sys_get_temp_dir();
$results['tmp_writable'] = is_writable(sys_get_temp_dir()) ? 'OK' : 'NON';

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
