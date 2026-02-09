<?php
/**
 * Script de tests de sécurité automatisés
 * Usage : php security-test.php
 * Génère un rapport dans security-report.md
 */

$baseUrl = 'http://localhost:3000';
$results = [];
$passed = 0;
$failed = 0;
$warnings = 0;

// ============================================================================
// Fonctions utilitaires
// ============================================================================

function request(string $method, string $url, ?array $body = null, array $headers = [], ?string $cookieFile = null): array {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            break;
        case 'OPTIONS':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
            break;
    }
    
    if ($body !== null) {
        $jsonBody = json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($jsonBody);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['code' => 0, 'headers' => '', 'body' => '', 'error' => $error, 'json' => null];
    }
    
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    $json = json_decode($responseBody, true);
    
    return [
        'code' => $httpCode,
        'headers' => $responseHeaders,
        'body' => $responseBody,
        'error' => null,
        'json' => $json
    ];
}

function hasHeader(string $headers, string $name): bool {
    return stripos($headers, $name . ':') !== false;
}

function getHeaderValue(string $headers, string $name): ?string {
    if (preg_match('/' . preg_quote($name, '/') . ':\s*(.+)/i', $headers, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function addResult(string $category, string $test, string $status, string $detail, int $httpCode = 0) {
    global $results, $passed, $failed, $warnings;
    
    $results[] = [
        'category' => $category,
        'test' => $test,
        'status' => $status,
        'detail' => $detail,
        'http_code' => $httpCode
    ];
    
    if ($status === 'PASS') $passed++;
    elseif ($status === 'FAIL') $failed++;
    elseif ($status === 'WARN') $warnings++;
}

echo "=== Tests de sécurité ViteGourmand ===\n\n";

// ============================================================================
// 1. TESTS DES SECURITY HEADERS
// ============================================================================
echo "[1/7] Test des Security Headers...\n";

$resp = request('POST', "$baseUrl/api/auth/login.php", ['email' => 'test@test.com', 'password' => 'test']);

if ($resp['error']) {
    addResult('Security Headers', 'Connexion au serveur', 'FAIL', "Erreur: {$resp['error']}");
} else {
    $headersToCheck = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1',
        'Strict-Transport-Security' => null,
        'Content-Security-Policy' => null,
        'Referrer-Policy' => null,
    ];
    
    foreach ($headersToCheck as $header => $expectedValue) {
        if (hasHeader($resp['headers'], $header)) {
            $value = getHeaderValue($resp['headers'], $header);
            if ($expectedValue && stripos($value, $expectedValue) === false) {
                addResult('Security Headers', $header, 'WARN', "Présent mais valeur inattendue: $value", $resp['code']);
            } else {
                addResult('Security Headers', $header, 'PASS', "Présent: $value", $resp['code']);
            }
        } else {
            addResult('Security Headers', $header, 'FAIL', 'Header absent', $resp['code']);
        }
    }
    
    // Vérifier Access-Control-Allow-Origin
    if (hasHeader($resp['headers'], 'Access-Control-Allow-Origin')) {
        $origin = getHeaderValue($resp['headers'], 'Access-Control-Allow-Origin');
        if ($origin === '*') {
            addResult('Security Headers', 'CORS Origin', 'FAIL', "Wildcard * détecté — risque de sécurité", $resp['code']);
        } else {
            addResult('Security Headers', 'CORS Origin', 'PASS', "Origine restreinte: $origin", $resp['code']);
        }
    } else {
        addResult('Security Headers', 'CORS Origin', 'PASS', 'Pas de header CORS (normal sans Origin)', $resp['code']);
    }
}

// ============================================================================
// 2. TESTS CSRF
// ============================================================================
echo "[2/7] Test de la protection CSRF...\n";

$cookieFile = tempnam(sys_get_temp_dir(), 'csrf_test_');

// 2a. Requête POST sans token CSRF → doit échouer
$resp = request('POST', "$baseUrl/api/auth/login.php", 
    ['email' => 'test@test.com', 'password' => 'test'],
    [],
    $cookieFile
);

if ($resp['code'] === 403) {
    addResult('CSRF', 'POST sans CSRF token', 'PASS', 'Rejeté avec 403 comme attendu', 403);
} else {
    addResult('CSRF', 'POST sans CSRF token', 'FAIL', "Attendu 403, reçu {$resp['code']}", $resp['code']);
}

// 2b. Récupérer un token CSRF
$csrfResp = request('GET', "$baseUrl/api/csrf/token.php", null, [], $cookieFile);
$csrfToken = $csrfResp['json']['csrf_token'] ?? null;

if ($csrfToken) {
    addResult('CSRF', 'Génération du token', 'PASS', 'Token reçu: ' . substr($csrfToken, 0, 12) . '...', 200);
    
    // 2c. Requête POST avec token CSRF valide
    $resp = request('POST', "$baseUrl/api/auth/login.php",
        ['email' => 'test@test.com', 'password' => 'test'],
        ["X-CSRF-Token: $csrfToken"],
        $cookieFile
    );
    
    if ($resp['code'] !== 403) {
        addResult('CSRF', 'POST avec CSRF token valide', 'PASS', "Accepté (HTTP {$resp['code']})", $resp['code']);
    } else {
        addResult('CSRF', 'POST avec CSRF token valide', 'FAIL', 'Toujours rejeté avec 403 malgré token valide', 403);
    }
    
    // 2d. Requête POST avec token CSRF invalide
    $resp = request('POST', "$baseUrl/api/auth/login.php",
        ['email' => 'test@test.com', 'password' => 'test'],
        ['X-CSRF-Token: token_invalide_12345'],
        $cookieFile
    );
    
    if ($resp['code'] === 403) {
        addResult('CSRF', 'POST avec CSRF token invalide', 'PASS', 'Rejeté avec 403 comme attendu', 403);
    } else {
        addResult('CSRF', 'POST avec CSRF token invalide', 'FAIL', "Attendu 403, reçu {$resp['code']}", $resp['code']);
    }
} else {
    addResult('CSRF', 'Génération du token', 'FAIL', 'Impossible de récupérer le token CSRF', $csrfResp['code']);
}

@unlink($cookieFile);

// ============================================================================
// 3. TESTS RATE LIMITING
// ============================================================================
echo "[3/7] Test du Rate Limiting...\n";

// Charger la config pour connaître les limites
$securityConfig = require __DIR__ . '/../config/security.php';
$loginLimit = $securityConfig['rate_limits']['login'] ?? ['requests' => 10, 'window' => 900];

$cookieFile = tempnam(sys_get_temp_dir(), 'rate_test_');

// Récupérer un token CSRF pour les tests
$csrfResp = request('GET', "$baseUrl/api/csrf/token.php", null, [], $cookieFile);
$csrfToken = $csrfResp['json']['csrf_token'] ?? '';

$rateLimitTriggered = false;
$lastCode = 0;
$totalSent = $loginLimit['requests'] + 2;

for ($i = 1; $i <= $totalSent; $i++) {
    $resp = request('POST', "$baseUrl/api/auth/login.php",
        ['email' => "ratetest{$i}@test.com", 'password' => 'wrong'],
        ["X-CSRF-Token: $csrfToken"],
        $cookieFile
    );
    $lastCode = $resp['code'];
    
    if ($resp['code'] === 429) {
        $rateLimitTriggered = true;
        addResult('Rate Limiting', "Login — limite atteinte à la requête $i", 'PASS', 
            "429 reçu après $i requêtes (limite configurée: {$loginLimit['requests']})", 429);
        break;
    }
}

if (!$rateLimitTriggered) {
    addResult('Rate Limiting', "Login — $totalSent requêtes envoyées", 'FAIL', 
        "Aucun 429 reçu après $totalSent requêtes (dernier code: $lastCode)", $lastCode);
}

// Vérifier les headers de rate limiting
$resp = request('POST', "$baseUrl/api/auth/login.php",
    ['email' => 'headertest@test.com', 'password' => 'wrong'],
    ["X-CSRF-Token: $csrfToken"],
    $cookieFile
);

if (hasHeader($resp['headers'], 'X-RateLimit-Limit')) {
    $limit = getHeaderValue($resp['headers'], 'X-RateLimit-Limit');
    $remaining = getHeaderValue($resp['headers'], 'X-RateLimit-Remaining');
    addResult('Rate Limiting', 'Headers X-RateLimit', 'PASS', "Limit: $limit, Remaining: $remaining", $resp['code']);
} else {
    addResult('Rate Limiting', 'Headers X-RateLimit', 'WARN', 'Headers X-RateLimit absents', $resp['code']);
}

@unlink($cookieFile);

// ============================================================================
// 4. TESTS INPUT VALIDATION
// ============================================================================
echo "[4/7] Test de la validation des entrées...\n";

$cookieFile = tempnam(sys_get_temp_dir(), 'input_test_');
$csrfResp = request('GET', "$baseUrl/api/csrf/token.php", null, [], $cookieFile);
$csrfToken = $csrfResp['json']['csrf_token'] ?? '';

// 4a. Mauvais Content-Type
$resp = request('POST', "$baseUrl/api/auth/register.php", null, 
    ["X-CSRF-Token: $csrfToken", "Content-Type: text/plain"],
    $cookieFile
);

if ($resp['code'] === 415) {
    addResult('Input Validation', 'Mauvais Content-Type', 'PASS', 'Rejeté avec 415 comme attendu', 415);
} else {
    addResult('Input Validation', 'Mauvais Content-Type', 'WARN', "Attendu 415, reçu {$resp['code']}", $resp['code']);
}

// 4b. Email invalide
$resp = request('POST', "$baseUrl/api/auth/login.php",
    ['email' => 'pas-un-email', 'password' => 'test'],
    ["X-CSRF-Token: $csrfToken"],
    $cookieFile
);

if ($resp['code'] >= 400 && $resp['code'] < 500) {
    $msg = $resp['json']['message'] ?? 'N/A';
    addResult('Input Validation', 'Email invalide rejeté', 'PASS', "HTTP {$resp['code']}: $msg", $resp['code']);
} else {
    addResult('Input Validation', 'Email invalide rejeté', 'FAIL', "Attendu 4xx, reçu {$resp['code']}", $resp['code']);
}

// 4c. Nom trop court (register)
$resp = request('POST', "$baseUrl/api/auth/register.php",
    ['email' => 'test@valid.com', 'password' => 'Test1234!@', 'confirmPassword' => 'Test1234!@',
     'firstName' => 'A', 'lastName' => 'B', 'phone' => '0612345678', 'address' => '123 rue de test ville'],
    ["X-CSRF-Token: $csrfToken"],
    $cookieFile
);

if ($resp['code'] >= 400 && $resp['code'] < 500) {
    $msg = $resp['json']['message'] ?? 'N/A';
    addResult('Input Validation', 'Nom trop court rejeté', 'PASS', "HTTP {$resp['code']}: $msg", $resp['code']);
} else {
    addResult('Input Validation', 'Nom trop court rejeté', 'WARN', "Attendu 4xx, reçu {$resp['code']}", $resp['code']);
}

@unlink($cookieFile);

// ============================================================================
// 5. TESTS CORS
// ============================================================================
echo "[5/7] Test CORS...\n";

// 5a. Requête OPTIONS (preflight)
$resp = request('OPTIONS', "$baseUrl/api/auth/login.php", null, [
    'Origin: http://localhost:3000',
    'Access-Control-Request-Method: POST',
    'Access-Control-Request-Headers: Content-Type, X-CSRF-Token'
]);

if ($resp['code'] === 200) {
    addResult('CORS', 'Preflight OPTIONS', 'PASS', 'Réponse 200 OK', 200);
} else {
    addResult('CORS', 'Preflight OPTIONS', 'WARN', "Code: {$resp['code']}", $resp['code']);
}

// 5b. Origine non autorisée
$resp = request('POST', "$baseUrl/api/auth/login.php",
    ['email' => 'test@test.com', 'password' => 'test'],
    ['Origin: http://evil-site.com']
);

$corsOrigin = getHeaderValue($resp['headers'], 'Access-Control-Allow-Origin');
if ($corsOrigin === null || $corsOrigin !== 'http://evil-site.com') {
    addResult('CORS', 'Origine malveillante bloquée', 'PASS', "Origin evil-site.com non reflété (valeur: " . ($corsOrigin ?? 'absent') . ")", $resp['code']);
} else {
    addResult('CORS', 'Origine malveillante bloquée', 'FAIL', "Origin evil-site.com accepté !", $resp['code']);
}

// ============================================================================
// 6. TESTS MASQUAGE DES ERREURS
// ============================================================================
echo "[6/7] Test du masquage des erreurs...\n";

$endpoints = [
    '/api/auth/login.php',
    '/api/auth/register.php',
    '/api/auth/reset-password.php',
    '/api/user/update-profile.php',
    '/api/user/confirm-email-change.php',
    '/api/user/confirm-delete-account.php',
    '/api/user/request-delete-account.php',
    '/api/user/request-password-reset.php',
    '/api/commands/user-commands.php',
];

$cookieFile = tempnam(sys_get_temp_dir(), 'error_test_');
$csrfResp = request('GET', "$baseUrl/api/csrf/token.php", null, [], $cookieFile);
$csrfToken = $csrfResp['json']['csrf_token'] ?? '';

foreach ($endpoints as $endpoint) {
    $resp = request('POST', "$baseUrl$endpoint",
        ['invalid' => 'data'],
        ["X-CSRF-Token: $csrfToken"],
        $cookieFile
    );
    
    $body = $resp['body'] ?? '';
    $hasStackTrace = (
        stripos($body, 'Stack trace') !== false ||
        stripos($body, 'Fatal error') !== false ||
        stripos($body, 'Warning:') !== false ||
        stripos($body, 'Notice:') !== false ||
        stripos($body, 'Parse error') !== false ||
        preg_match('/\.php:\d+/', $body)
    );
    
    $name = basename($endpoint);
    if ($hasStackTrace) {
        addResult('Masquage erreurs', $name, 'FAIL', 'Informations PHP exposées dans la réponse', $resp['code']);
    } else {
        addResult('Masquage erreurs', $name, 'PASS', 'Aucune fuite d\'information détectée', $resp['code']);
    }
}

@unlink($cookieFile);

// ============================================================================
// 7. TEST MÉTHODES HTTP NON AUTORISÉES
// ============================================================================
echo "[7/7] Test des méthodes HTTP non autorisées...\n";

$postOnlyEndpoints = [
    '/api/auth/login.php',
    '/api/auth/register.php',
    '/api/auth/forgot-password.php',
];

foreach ($postOnlyEndpoints as $endpoint) {
    $resp = request('GET', "$baseUrl$endpoint");
    $name = basename($endpoint);
    
    if ($resp['code'] === 405) {
        addResult('Méthodes HTTP', "$name — GET bloqué", 'PASS', 'Rejeté avec 405 Method Not Allowed', 405);
    } else {
        addResult('Méthodes HTTP', "$name — GET bloqué", 'WARN', "Attendu 405, reçu {$resp['code']}", $resp['code']);
    }
}

// ============================================================================
// GÉNÉRATION DU RAPPORT
// ============================================================================
echo "\n=== Génération du rapport ===\n";

$date = date('Y-m-d H:i:s');
$total = $passed + $failed + $warnings;

$report = "# Rapport de Tests de Sécurité — ViteGourmand\n\n";
$report .= "**Date :** $date  \n";
$report .= "**Serveur :** $baseUrl  \n";
$report .= "**PHP :** " . PHP_VERSION . "  \n\n";
$report .= "---\n\n";
$report .= "## Résumé\n\n";
$report .= "| Résultat | Nombre |\n";
$report .= "|----------|--------|\n";
$report .= "| ✅ PASS  | $passed |\n";
$report .= "| ❌ FAIL  | $failed |\n";
$report .= "| ⚠️ WARN  | $warnings |\n";
$report .= "| **Total** | **$total** |\n\n";

$scorePercent = $total > 0 ? round(($passed / $total) * 100) : 0;
$report .= "**Score global : {$scorePercent}%**\n\n";
$report .= "---\n\n";

// Grouper par catégorie
$categories = [];
foreach ($results as $r) {
    $categories[$r['category']][] = $r;
}

foreach ($categories as $cat => $tests) {
    $report .= "## $cat\n\n";
    $report .= "| Test | Résultat | HTTP | Détail |\n";
    $report .= "|------|----------|------|--------|\n";
    
    foreach ($tests as $t) {
        $icon = match($t['status']) {
            'PASS' => '✅',
            'FAIL' => '❌',
            'WARN' => '⚠️',
            default => '❓'
        };
        $code = $t['http_code'] > 0 ? $t['http_code'] : '—';
        $detail = str_replace('|', '\\|', $t['detail']);
        $report .= "| {$t['test']} | $icon {$t['status']} | $code | $detail |\n";
    }
    
    $report .= "\n";
}

$report .= "---\n\n";
$report .= "## Configuration de sécurité active\n\n";
$report .= "```php\n";
$report .= "// Extrait de backend/config/security.php\n";
$report .= "'rate_limits' => [\n";
foreach ($securityConfig['rate_limits'] as $action => $limit) {
    $report .= "    '$action' => {$limit['requests']} req / {$limit['window']}s,\n";
}
$report .= "]\n";
$report .= "'csrf.token_lifetime' => " . ($securityConfig['csrf']['token_lifetime'] ?? 3600) . "s\n";
$report .= "'input.max_input_size' => " . ($securityConfig['input']['max_input_size'] ?? '1MB') . " bytes\n";
$report .= "```\n\n";

$report .= "---\n\n";
$report .= "*Rapport généré automatiquement par `backend/tests/security-test.php`*\n";

// Écrire le rapport
$reportPath = __DIR__ . '/security-report.md';
file_put_contents($reportPath, $report);

echo "\n";
echo "========================================\n";
echo "  RÉSULTATS : $passed PASS / $failed FAIL / $warnings WARN\n";
echo "  Score : {$scorePercent}%\n";
echo "  Rapport : backend/tests/security-report.md\n";
echo "========================================\n";
