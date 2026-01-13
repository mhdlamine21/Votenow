<?php
/**
 * VoteNow - Configuration et helpers centraux
 * Librairies utilisées :
 *   - PDO natif PHP (requêtes préparées, protection injection SQL)
 *   - password_hash/verify natif PHP (bcrypt)
 *   - openssl_random_pseudo_bytes pour CSRF token (natif PHP) */

// Environnement 
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', APP_ENV === 'development');

// Affichage erreurs selon env
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Charger .env si présent localement
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!getenv($key)) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }
}

// DB Config 
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'votenow_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// Session sécurisée 
// Fix CRITIQUE : session sécurisée (voir audit faille #3)
function secureSessionStart(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', (int)(getenv('SESSION_LIFETIME') ?: 1800));

    session_start();

    // Timeout d'inactivité : 30 min
    $timeout = (int)(getenv('SESSION_LIFETIME') ?: 1800);
    if (isset($_SESSION['_last_activity'])) {
        if (time() - $_SESSION['_last_activity'] > $timeout) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    $_SESSION['_last_activity'] = time();
}

// PDO singleton 
function db(): PDO {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                ]
            );
        } catch (PDOException $e) {
            // Fix CRITIQUE : ne jamais exposer les détails de connexion (faille #4)
            error_log('[VoteNow DB] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur interne. Réessayez.'], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    return $conn;
}

// Query helpers 
function query(string $sql, array $p = []): array {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
}

function queryOne(string $sql, array $p = []): ?array {
    $s = db()->prepare($sql);
    $s->execute($p);
    $r = $s->fetch();
    return $r ?: null;
}

function execute(string $sql, array $p = []): int {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s->rowCount();
}

function lastId(): string {
    return db()->lastInsertId();
}

function getSetting(string $key, string $default = ''): string {
    $r = queryOne("SELECT valeur FROM settings WHERE cle = ?", [$key]);
    return $r ? $r['valeur'] : $default;
}

// CSRF 
// Fix CRITIQUE : protection CSRF (faille #2) - génération native PHP sécurisée
function csrfToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function verifyCsrf(array $data = []): void {
    // Accepte depuis header HTTP ou body JSON
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $data['_csrf']
          ?? '';

    if (empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        jsonOut(['success' => false, 'message' => 'Token de sécurité invalide. Rechargez la page.'], 403);
    }
}

// Rate limiting 
// Fix ÉLEVÉ : rate limiting par IP (faille #1)
// Utilise la table logs existante - aucune dépendance externe
function checkRateLimit(string $ip, string $action, int $max = 15, int $windowSec = 300): void {
    try {
        $count = queryOne(
            "SELECT COUNT(*) as n FROM logs WHERE ip = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, $action, $windowSec]
        );
        if ((int)($count['n'] ?? 0) >= $max) {
            http_response_code(429);
            jsonOut(['success' => false, 'message' => 'Trop de tentatives. Attendez quelques minutes.'], 429);
        }
    } catch (Exception $e) {
        // Ne pas bloquer si la table logs est indisponible
    }
}

// Validation upload 
// Fix ÉLEVÉ : vérification MIME réelle avec finfo (faille upload)
function validateUploadImage(array $file, int $maxBytes = 5242880): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors de l\'upload du fichier.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image trop lourde (max ' . ($maxBytes / 1048576) . ' Mo).');
    }

    // Vérification MIME réelle - pas $_FILES['type'] qui vient du client
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($realMime, $allowed, true)) {
        throw new RuntimeException('Format image non autorisé. Utilisez JPG, PNG ou WEBP.');
    }

    // Vérification magic bytes image
    $img = @getimagesize($file['tmp_name']);
    if ($img === false) {
        throw new RuntimeException('Fichier image corrompu ou invalide.');
    }

    return $realMime;
}

function validateUploadPDF(array $file, int $maxBytes = 10485760): void {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors de l\'upload du PDF.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('PDF trop lourd (max ' . ($maxBytes / 1048576) . ' Mo).');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);

    if ($realMime !== 'application/pdf') {
        throw new RuntimeException('Seuls les fichiers PDF sont acceptés.');
    }

    // Vérification magic bytes PDF : %PDF
    $handle = fopen($file['tmp_name'], 'rb');
    $magic  = fread($handle, 4);
    fclose($handle);
    if ($magic !== '%PDF') {
        throw new RuntimeException('Fichier PDF invalide.');
    }
}

// Validation longueurs 
// Fix ÉLEVÉ : validation stricte des longueurs (faille #5)
function validateMaxLength(string $value, int $max, string $fieldName): string {
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') > $max) {
        throw new RuntimeException("$fieldName dépasse $max caractères.");
    }
    return $value;
}

function requireField(string $value, string $fieldName): string {
    $value = trim($value);
    if ($value === '') {
        throw new RuntimeException("Le champ $fieldName est obligatoire.");
    }
    return $value;
}

// Sanitize 
function sanitize(string $val): string {
    return trim(strip_tags($val));
}

// Auth guards 
function requireAuth(): array {
    if (!isset($_SESSION['user_id'])) {
        jsonOut(['success' => false, 'message' => 'Connexion requise.', 'redirect' => 'login'], 401);
    }
    return $_SESSION['user_data'] ?? [];
}

function requireAdmin(): array {
    if (!isset($_SESSION['admin_id'])) {
        jsonOut(['success' => false, 'message' => 'Accès réservé aux administrateurs.'], 403);
    }
    return $_SESSION['admin_data'] ?? [];
}

function requireSuperAdmin(): array {
    $admin = requireAdmin();
    if (($admin['role'] ?? '') !== 'superadmin') {
        jsonOut(['success' => false, 'message' => 'Réservé au super administrateur.'], 403);
    }
    return $admin;
}

// Honeypot 
// Fix : protection bot honeypot (champ caché côté HTML)
function honeypot(array $data): void {
    if (!empty($data['website']) || !empty($data['_hp']) || !empty($data['phone_number'])) {
        // Simuler une réponse normale pour ne pas alerter le bot
        jsonOut(['success' => false, 'message' => 'Données invalides.'], 400);
    }
}

// Output 
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

// Logging 
function logAction(string $type, string $action, ?int $userId = null, ?int $adminId = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Tronquer l'IP pour les logs (RGPD : pas de log IP complète en production)
        if (APP_ENV !== 'development' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $ip = $parts[0] . '.' . $parts[1] . '.x.x';
        }
        execute(
            "INSERT INTO logs (type, action, utilisateur_id, admin_id, ip) VALUES (?, ?, ?, ?, ?)",
            [$type, mb_substr($action, 0, 500), $userId, $adminId, $ip]
        );
    } catch (Exception $e) {
        error_log('[VoteNow LOG] ' . $e->getMessage());
    }
}

// Notifications 
function notifyUser(int $userId, string $titre, string $message, ?string $lien = null): void {
    try {
        execute(
            "INSERT INTO notifications (utilisateur_id, titre, message, lien) VALUES (?, ?, ?, ?)",
            [$userId, mb_substr($titre, 0, 200), mb_substr($message, 0, 1000), $lien]
        );
    } catch (Exception $e) {
        error_log('[VoteNow NOTIF] ' . $e->getMessage());
    }
}

// Eligibilité 
function isEligible(array $user, array $election): bool {
    switch ($election['scope'] ?? '') {
        case 'universite': return true;
        case 'ufr':        return (int)($user['ufr_id'] ?? 0) === (int)$election['ufr_id'];
        case 'filiere':    return (int)($user['filiere_id'] ?? 0) === (int)$election['filiere_id'];
        case 'niveau':
            return (int)($user['filiere_id'] ?? 0) === (int)$election['filiere_id']
                && ($user['niveau'] ?? '') === $election['niveau'];
        default: return false;
    }
}
?>
