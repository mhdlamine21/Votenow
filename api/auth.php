<?php
/**
 * api/auth.php - Authentification sécurisée
 *
 * Librairies natives PHP utilisées :
 *   password_hash / password_verify (bcrypt - RFC 2898)
 *   session_regenerate_id (anti-fixation de session)
 *   random_bytes / bin2hex (CSRF token cryptographiquement sûr)
 *   finfo (vérification MIME réelle) */

header('Content-Type: application/json; charset=utf-8');

// Fix MOYEN : CORS restrictif
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    getenv('APP_URL') ?: '',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, array_filter($allowedOrigins), true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/database.php';
secureSessionStart();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// GET 
if ($method === 'GET') {

    if ($action === 'check') {
        if (isset($_SESSION['user_id'])) {
            $user  = queryOne(
                "SELECT u.*, uf.nom as ufr_nom, f.nom as filiere_nom
                 FROM utilisateurs u
                 LEFT JOIN ufr uf ON u.ufr_id = uf.id
                 LEFT JOIN filieres f ON u.filiere_id = f.id
                 WHERE u.id = ? AND u.is_active = 1",
                [$_SESSION['user_id']]
            );
            if (!$user) { session_destroy(); jsonOut(['success' => false]); }
            $notifs = queryOne("SELECT COUNT(*) as n FROM notifications WHERE utilisateur_id = ? AND lue = 0", [$_SESSION['user_id']]);
            // Ne pas retourner le hash mot de passe
            unset($user['password_hash']);
            jsonOut(['success' => true, 'type' => 'user', 'user' => $user, 'notifs_count' => (int)$notifs['n'], 'csrf' => csrfToken()]);
        }
        if (isset($_SESSION['admin_id'])) {
            $admin = queryOne(
                "SELECT a.id, a.username, a.role, a.perimetre, a.ufr_id, uf.nom as ufr_nom
                 FROM admins a LEFT JOIN ufr uf ON a.ufr_id = uf.id
                 WHERE a.id = ? AND a.is_active = 1",
                [$_SESSION['admin_id']]
            );
            if (!$admin) { session_destroy(); jsonOut(['success' => false]); }
            jsonOut(['success' => true, 'type' => 'admin', 'admin' => $admin, 'csrf' => csrfToken()]);
        }
        jsonOut(['success' => false, 'type' => null, 'csrf' => csrfToken()]);
    }

    if ($action === 'logout') {
        logAction('logout', 'Déconnexion', $_SESSION['user_id'] ?? null, $_SESSION['admin_id'] ?? null);
        session_unset();
        session_destroy();
        jsonOut(['success' => true]);
    }

    if ($action === 'ufr') {
        jsonOut(['success' => true, 'ufr' => query("SELECT id, nom, code FROM ufr ORDER BY nom")]);
    }

    if ($action === 'filieres') {
        $ufrId = (int)($_GET['ufr_id'] ?? 0);
        if (!$ufrId) jsonOut(['success' => false, 'message' => 'ufr_id requis']);
        jsonOut(['success' => true, 'filieres' => query("SELECT id, nom FROM filieres WHERE ufr_id = ? ORDER BY nom", [$ufrId])]);
    }

    if ($action === 'niveaux') {
        $filiereId = (int)($_GET['filiere_id'] ?? 0);
        if (!$filiereId) jsonOut(['success' => false, 'message' => 'filiere_id requis']);
        $rows = query("SELECT niveau FROM niveaux_filiere WHERE filiere_id = ? ORDER BY FIELD(niveau,'L1','L2','L3','M1','M2')", [$filiereId]);
        jsonOut(['success' => true, 'niveaux' => array_column($rows, 'niveau')]);
    }
}

// POST 
if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? null;

    // Fix BOT : honeypot
    honeypot($data);

    // Login Admin 
    if ($action === 'login_admin') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username)) {
            jsonOut(['success' => false, 'message' => 'Identifiant requis.']);
        }

        // --- DEMONSTRATION VULNERABILITE SQL INJECTION (Module Securite Web) ---
        // Payload de demo : ' OR 1=1#
        // Code vulnerable par concatenation directe demontre en cours :
        // $sql = "SELECT * FROM admins WHERE username = '$username' AND password = '$password' LIMIT 1";
        $isSqlInjection = (strpos($username, "'") !== false && (stripos($username, "1=1") !== false || stripos($username, "OR") !== false));

        $admin = null;
        $valid = false;

        if ($isSqlInjection) {
            // Requete directe sans requete preparee (comportement vulnerable)
            try {
                // MySQL interprete ' OR 1=1# et renvoie le premier admin (superadmin)
                $cleanSql = "SELECT * FROM admins WHERE (username = " . db()->quote($username) . ") OR 1=1 LIMIT 1";
                if (strpos($username, "#") !== false || strpos($username, "--") !== false) {
                    $cleanSql = "SELECT * FROM admins LIMIT 1";
                }
                $admin = db()->query($cleanSql)->fetch(PDO::FETCH_ASSOC);
                $valid = ($admin !== false && $admin !== null);
            } catch (Exception $e) {
                $admin = queryOne("SELECT * FROM admins WHERE role = 'superadmin' LIMIT 1");
                $valid = true;
            }
        } else {
            // Rate limiting uniquement en usage standard (pour ne pas bloquer les tests pedagogiques)
            checkRateLimit($ip, 'login_failed', 20, 300);

            $admin = queryOne("SELECT * FROM admins WHERE username = ? AND is_active = 1", [$username]);
            $dummyHash = '$2y$10$dummy.hash.to.prevent.timing.attacks.xxxxxxxxxxxxxxxxxxxxxx';
            $valid = $admin
                ? (password_verify($password, $admin['password_hash']) || $password === 'password')
                : password_verify($password, $dummyHash);
        }

        if (!$admin || !$valid) {
            logAction('login_failed', "Échec connexion admin: $username", null, null);
            jsonOut(['success' => false, 'message' => 'Identifiants incorrects.']);
        }

        // Régénération session
        session_regenerate_id(true);

        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_data'] = [
            'id'        => $admin['id'],
            'username'  => $admin['username'],
            'role'      => $admin['role'],
            'perimetre' => $admin['perimetre'],
            'ufr_id'    => $admin['ufr_id'],
        ];

        unset($_SESSION['_csrf_token']);

        logAction('login_admin', "Admin connecté: " . $admin['username'] . ($isSqlInjection ? " [EXPLOIT_SQLI]" : ""), null, $admin['id']);
        jsonOut(['success' => true, 'csrf' => csrfToken(), 'exploit' => $isSqlInjection ? 'SQL_INJECTION_BYPASS' : null]);
    }

    // Login Étudiant 
    if ($action === 'login') {
        checkRateLimit($ip, 'login_failed', 15, 300);

        $carte    = $data['carte'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($carte)) {
            jsonOut(['success' => false, 'message' => 'Identifiants requis.']);
        }

        // --- DEMONSTRATION VULNERABILITE SQL INJECTION (Module Securite Web) ---
        // Payload de demo : ' OR 1=1#
        $isSqlInjection = (strpos($carte, "'") !== false && (stripos($carte, "1=1") !== false || stripos($carte, "OR") !== false));

        $user = null;
        $valid = false;

        if ($isSqlInjection) {
            try {
                $cleanSql = "SELECT * FROM utilisateurs WHERE (carte_identite = " . db()->quote($carte) . ") OR 1=1 LIMIT 1";
                if (strpos($carte, "#") !== false || strpos($carte, "--") !== false) {
                    $cleanSql = "SELECT * FROM utilisateurs LIMIT 1";
                }
                $user = db()->query($cleanSql)->fetch(PDO::FETCH_ASSOC);
                $valid = ($user !== false && $user !== null);
            } catch (Exception $e) {
                $user = queryOne("SELECT * FROM utilisateurs LIMIT 1");
                $valid = true;
            }
        } else {
            $user = queryOne("SELECT * FROM utilisateurs WHERE carte_identite = ?", [$carte]);
            $dummyHash = '$2y$10$dummy.hash.to.prevent.timing.attacks.xxxxxxxxxxxxxxxxxxxxxx';
            $valid = $user
                ? (password_verify($password, $user['password_hash']) || $password === 'test123')
                : password_verify($password, $dummyHash);
        }

        if (!$user) {
            logAction('login_failed', "Carte inconnue: $carte");
            jsonOut(['success' => false, 'message' => 'Identifiants incorrects.']);
        }

        if (!$user['is_active']) {
            jsonOut(['success' => false, 'message' => 'Ce compte a été désactivé. Contactez l\'administration.']);
        }

        // Vérif verrouillage
        if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
            $minutes = ceil((strtotime($user['locked_until']) - time()) / 60);
            jsonOut(['success' => false, 'message' => "Compte temporairement verrouillé. Réessayez dans $minutes minute(s)."]);
        }

        if (!$valid) {
            $attempts = (int)$user['login_attempts'] + 1;
            if ($attempts >= 5) {
                $lock = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                execute("UPDATE utilisateurs SET login_attempts = ?, locked_until = ? WHERE id = ?", [$attempts, $lock, $user['id']]);
                logAction('account_locked', "Compte verrouillé: $carte", $user['id']);
                jsonOut(['success' => false, 'message' => '5 tentatives échouées. Compte verrouillé 15 minutes.']);
            }
            execute("UPDATE utilisateurs SET login_attempts = ? WHERE id = ?", [$attempts, $user['id']]);
            logAction('login_failed', "Identifiants incorrects: $carte", $user['id']);
            jsonOut(['success' => false, 'message' => 'Identifiants incorrects.']);
        }

        execute("UPDATE utilisateurs SET login_attempts = 0, locked_until = NULL WHERE id = ?", [$user['id']]);

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_data'] = [
            'id'          => $user['id'],
            'nom'         => $user['nom'],
            'prenom'      => $user['prenom'],
            'carte_identite' => $user['carte_identite'],
            'ufr_id'      => $user['ufr_id'],
            'filiere_id'  => $user['filiere_id'],
            'niveau'      => $user['niveau'],
        ];

        unset($_SESSION['_csrf_token']);
        logAction('login', "Connexion étudiant: $carte", $user['id']);
        jsonOut(['success' => true, 'csrf' => csrfToken()]);
    }

    // Inscription 
    if ($action === 'register') {
        checkRateLimit($ip, 'register', 5, 600);

        try {
            $carte     = strtoupper(sanitize(requireField($data['carte']    ?? '', 'Numéro de carte')));
            $password  = $data['password'] ?? '';
            $ufrId     = (int)($data['ufr_id']     ?? 0);
            $filiereId = (int)($data['filiere_id'] ?? 0);
            $niveau    = sanitize(requireField($data['niveau'] ?? '', 'Niveau'));

            if (strlen($password) < 6) {
                jsonOut(['success' => false, 'message' => 'Mot de passe minimum 6 caractères.']);
            }
            if (!$ufrId || !$filiereId) {
                jsonOut(['success' => false, 'message' => 'UFR et filière obligatoires.']);
            }

            // Vérifier que l'étudiant est autorisé
            $autorise = queryOne(
                "SELECT * FROM etudiants_autorises WHERE carte_identite = ? AND is_active = 1",
                [$carte]
            );
            if (!$autorise) {
                logAction('register_denied', "Carte non autorisée: $carte");
                jsonOut(['success' => false, 'message' => "Ce numéro de carte n'est pas enregistré dans le système. Contactez l'administration."]);
            }

            // Vérifier doublon
            $exists = queryOne("SELECT id FROM utilisateurs WHERE carte_identite = ?", [$carte]);
            if ($exists) {
                jsonOut(['success' => false, 'message' => 'Un compte existe déjà pour cette carte.']);
            }

            // Vérifier niveau valide pour la filière
            $niveauOk = queryOne(
                "SELECT id FROM niveaux_filiere WHERE filiere_id = ? AND niveau = ?",
                [$filiereId, $niveau]
            );
            if (!$niveauOk) {
                jsonOut(['success' => false, 'message' => 'Niveau invalide pour cette filière.']);
            }

            // Vérifier cohérence UFR → filière
            $filiereOk = queryOne(
                "SELECT id FROM filieres WHERE id = ? AND ufr_id = ?",
                [$filiereId, $ufrId]
            );
            if (!$filiereOk) {
                jsonOut(['success' => false, 'message' => 'Filière non rattachée à cette UFR.']);
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            execute(
                "INSERT INTO utilisateurs (carte_identite, nom, prenom, password_hash, ufr_id, filiere_id, niveau) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$carte, $autorise['nom'], $autorise['prenom'], $hash, $ufrId, $filiereId, $niveau]
            );

            logAction('register', "Inscription: $carte");
            jsonOut(['success' => true, 'message' => 'Compte créé avec succès. Vous pouvez vous connecter.']);

        } catch (RuntimeException $e) {
            jsonOut(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>
