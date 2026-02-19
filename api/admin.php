<?php
header('Content-Type: application/json; charset=utf-8');
// CORS handled per-origin - see .htaccess
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

secureSessionStart();
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$section = $_GET['section'] ?? $_GET['action'] ?? 'dashboard';

if ($method === 'GET') {
    $admin = requireAdmin();

    if ($section === 'export_backup') {
        requireSuperAdmin();
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="votenow_backup_' . date('Y-m-d_H-i-s') . '.sql"');
        echo "-- VoteNow Database Backup\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $tables = ['settings', 'ufr', 'filieres', 'admins', 'utilisateurs', 'etudiants_autorises', 'elections', 'candidatures', 'participations', 'votes', 'demandes_profil', 'notifications', 'logs'];
        foreach ($tables as $t) {
            try {
                $rows = query("SELECT * FROM `$t`");
                if (empty($rows)) continue;
                echo "-- Data for table `$t`\n";
                foreach ($rows as $r) {
                    $cols = array_keys($r);
                    $vals = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return db()->quote($v);
                    }, array_values($r));
                    echo "INSERT INTO `$t` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ");\n";
                }
                echo "\n";
            } catch (Exception $e) {}
        }
        exit();
    }

    if ($section === 'dashboard') {
        $data = [
            'total_elections' => queryOne("SELECT COUNT(*) as n FROM elections")['n'],
            'elections_actives' => queryOne("SELECT COUNT(*) as n FROM elections WHERE phase='vote'")['n'],
            'total_etudiants' => queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1")['n'],
            'total_votes' => queryOne("SELECT COUNT(*) as n FROM participations")['n'],
            'candidatures_attente' => queryOne("SELECT COUNT(*) as n FROM candidatures WHERE statut='en_attente'")['n'],
            'demandes_profil' => queryOne("SELECT COUNT(*) as n FROM demandes_profil WHERE statut='en_attente'")['n'],
            'comptes_verrouilles' => queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE locked_until > NOW()")['n'],
        ];
        jsonOut(['success'=>true,'stats'=>$data]);
    }

    if ($section === 'etudiants') {
        requireSuperAdmin();
        $search = $_GET['q'] ?? '';
        $params = [];
        $where = 'WHERE 1=1';
        if ($search) { $where .= ' AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.carte_identite LIKE ?)'; $params = ["%$search%","%$search%","%$search%"]; }
        $etudiants = query("SELECT u.*, uf.nom as ufr_nom, f.nom as filiere_nom FROM utilisateurs u LEFT JOIN ufr uf ON u.ufr_id=uf.id LEFT JOIN filieres f ON u.filiere_id=f.id $where ORDER BY u.nom LIMIT 100", $params);
        jsonOut(['success'=>true,'etudiants'=>$etudiants]);
    }

    if ($section === 'etudiants_autorises') {
        requireSuperAdmin();
        $etudiants = query("SELECT ea.*, uf.nom as ufr_nom, f.nom as filiere_nom FROM etudiants_autorises ea LEFT JOIN ufr uf ON ea.ufr_id=uf.id LEFT JOIN filieres f ON ea.filiere_id=f.id ORDER BY ea.nom LIMIT 200");
        jsonOut(['success'=>true,'etudiants'=>$etudiants]);
    }

    if ($section === 'admins') {
        requireSuperAdmin();
        $admins = query("SELECT a.id, a.username, a.role, a.perimetre, a.is_active, a.created_at, uf.nom as ufr_nom FROM admins a LEFT JOIN ufr uf ON a.ufr_id=uf.id ORDER BY a.role, a.username");
        jsonOut(['success'=>true,'admins'=>$admins]);
    }

    if ($section === 'ufr') {
        $ufrs = query("SELECT u.*, COUNT(f.id) as nb_filieres FROM ufr u LEFT JOIN filieres f ON f.ufr_id=u.id GROUP BY u.id ORDER BY u.nom");
        jsonOut(['success'=>true,'ufr'=>$ufrs]);
    }

    if ($section === 'filieres') {
        $ufrId = (int)($_GET['ufr_id'] ?? 0);
        $where = $ufrId ? 'WHERE f.ufr_id=?' : '';
        $params = $ufrId ? [$ufrId] : [];
        $filieres = query("SELECT f.*, uf.nom as ufr_nom, GROUP_CONCAT(n.niveau ORDER BY FIELD(n.niveau,'L1','L2','L3','M1','M2') SEPARATOR ',') as niveaux FROM filieres f LEFT JOIN ufr uf ON f.ufr_id=uf.id LEFT JOIN niveaux_filiere n ON n.filiere_id=f.id $where GROUP BY f.id ORDER BY uf.nom, f.nom", $params);
        jsonOut(['success'=>true,'filieres'=>$filieres]);
    }

    if ($section === 'logs') {
        requireSuperAdmin();
        $type = $_GET['type'] ?? null;
        $params = [];
        $where = 'WHERE 1=1';
        if ($type) { $where .= ' AND l.type=?'; $params[] = $type; }
        $logs = query("SELECT l.*, u.nom, u.prenom FROM logs l LEFT JOIN utilisateurs u ON l.utilisateur_id=u.id $where ORDER BY l.created_at DESC LIMIT 200", $params);
        jsonOut(['success'=>true,'logs'=>$logs]);
    }

    if ($section === 'demandes_profil') {
        requireSuperAdmin();
        $demandes = query("SELECT d.*, u.nom, u.prenom, u.carte_identite, uf.nom as ufr_nom, f.nom as filiere_nom FROM demandes_profil d JOIN utilisateurs u ON d.utilisateur_id=u.id LEFT JOIN ufr uf ON d.ufr_id=uf.id LEFT JOIN filieres f ON d.filiere_id=f.id WHERE d.statut='en_attente' ORDER BY d.created_at");
        jsonOut(['success'=>true,'demandes'=>$demandes]);
    }

    if ($section === 'settings') {
        requireSuperAdmin();
        $settings = query("SELECT cle, valeur FROM settings");
        $map = [];
        foreach ($settings as $s) $map[$s['cle']] = $s['valeur'];
        jsonOut(['success'=>true,'settings'=>$map]);
    }

    if ($section === 'candidatures_attente') {
        $elId = (int)($_GET['election_id'] ?? 0);
        $where = $elId ? 'AND c.election_id=?' : '';
        $params = $elId ? [$elId] : [];
        $candidatures = query("SELECT c.*, e.titre as election_titre, u.nom, u.prenom, u.carte_identite FROM candidatures c JOIN elections e ON c.election_id=e.id LEFT JOIN utilisateurs u ON c.utilisateur_id=u.id WHERE c.statut='en_attente' $where ORDER BY c.created_at", $params);
        jsonOut(['success'=>true,'candidatures'=>$candidatures]);
    }
}

if ($method === 'POST') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'create_admin') {
        requireSuperAdmin();
        $username = sanitize($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'admin';
        $perimetre = $data['perimetre'] ?? 'universite';
        $ufrId = !empty($data['ufr_id']) ? (int)$data['ufr_id'] : null;
        $filiereId = !empty($data['filiere_id']) ? (int)$data['filiere_id'] : null;

        if (!$username || !$password) jsonOut(['success'=>false,'message'=>'Champs obligatoires']);
        if (strlen($password) < 8) jsonOut(['success'=>false,'message'=>'Mot de passe minimum 8 caractères']);

        $exists = queryOne("SELECT id FROM admins WHERE username=?", [$username]);
        if ($exists) jsonOut(['success'=>false,'message'=>'Ce nom d\'utilisateur existe déjà']);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        execute("INSERT INTO admins (username, password_hash, role, perimetre, ufr_id, filiere_id) VALUES (?,?,?,?,?,?)",
            [$username, $hash, $role, $perimetre, $ufrId, $filiereId]);
        logAction('create_admin', "Admin créé: $username ($role)", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Compte admin créé']);
    }

    if ($action === 'toggle_admin') {
        requireSuperAdmin();
        $id = (int)($data['id'] ?? 0);
        $a = queryOne("SELECT * FROM admins WHERE id=?", [$id]);
        if (!$a) jsonOut(['success'=>false,'message'=>'Admin introuvable']);
        if ($a['role'] === 'superadmin') jsonOut(['success'=>false,'message'=>'Impossible de désactiver le superadmin']);
        execute("UPDATE admins SET is_active=? WHERE id=?", [$a['is_active'] ? 0 : 1, $id]);
        jsonOut(['success'=>true,'message'=>$a['is_active'] ? 'Admin désactivé' : 'Admin activé']);
    }

    if ($action === 'create_ufr') {
        requireSuperAdmin();
        $nom = sanitize($data['nom'] ?? '');
        $code = sanitize($data['code'] ?? '');
        $desc = sanitize($data['description'] ?? '');
        if (!$nom || !$code) jsonOut(['success'=>false,'message'=>'Nom et code obligatoires']);
        execute("INSERT INTO ufr (nom, code, description) VALUES (?,?,?)", [$nom, $code, $desc]);
        logAction('create_ufr', "UFR créée: $nom", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'UFR créée','id'=>lastId()]);
    }

    if ($action === 'create_filiere') {
        requireSuperAdmin();
        $ufrId = (int)($data['ufr_id'] ?? 0);
        $nom = sanitize($data['nom'] ?? '');
        $niveaux = $data['niveaux'] ?? [];
        if (!$ufrId || !$nom) jsonOut(['success'=>false,'message'=>'UFR et nom obligatoires']);
        execute("INSERT INTO filieres (ufr_id, nom) VALUES (?,?)", [$ufrId, $nom]);
        $fId = lastId();
        foreach ($niveaux as $niv) {
            $niv = sanitize($niv);
            if (in_array($niv, ['L1','L2','L3','M1','M2'])) {
                execute("INSERT IGNORE INTO niveaux_filiere (filiere_id, niveau) VALUES (?,?)", [$fId, $niv]);
            }
        }
        logAction('create_filiere', "Filière créée: $nom", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Filière créée','id'=>$fId]);
    }

    if ($action === 'delete_ufr') {
        requireSuperAdmin();
        $id = (int)($data['id'] ?? 0);
        execute("DELETE FROM ufr WHERE id=?", [$id]);
        jsonOut(['success'=>true,'message'=>'UFR supprimée']);
    }

    if ($action === 'delete_filiere') {
        requireSuperAdmin();
        $id = (int)($data['id'] ?? 0);
        execute("DELETE FROM filieres WHERE id=?", [$id]);
        jsonOut(['success'=>true,'message'=>'Filière supprimée']);
    }

    if ($action === 'save_settings') {
        requireSuperAdmin();
        $allowed = ['app_name','universite_nom','couleur_principale','message_accueil','contact_admin'];
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                execute("INSERT INTO settings (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)",
                    [$key, sanitize($data[$key])]);
            }
        }
        jsonOut(['success'=>true,'message'=>'Paramètres sauvegardés']);
    }

    if ($action === 'import_etudiants') {
        requireSuperAdmin();
        $rows = $data['rows'] ?? [];
        $added = 0; $updated = 0; $errors = 0;
        foreach ($rows as $row) {
            try {
                $carte = sanitize($row['carte'] ?? '');
                $nom = sanitize($row['nom'] ?? '');
                $prenom = sanitize($row['prenom'] ?? '');
                $ufrId = !empty($row['ufr_id']) ? (int)$row['ufr_id'] : null;
                $filiereId = !empty($row['filiere_id']) ? (int)$row['filiere_id'] : null;
                $niveau = sanitize($row['niveau'] ?? '');
                if (!$carte || !$nom || !$prenom) { $errors++; continue; }
                $exists = queryOne("SELECT id FROM etudiants_autorises WHERE carte_identite=?", [$carte]);
                if ($exists) {
                    execute("UPDATE etudiants_autorises SET nom=?, prenom=?, ufr_id=?, filiere_id=?, niveau=? WHERE carte_identite=?",
                        [$nom, $prenom, $ufrId, $filiereId, $niveau, $carte]);
                    $updated++;
                } else {
                    execute("INSERT INTO etudiants_autorises (carte_identite, nom, prenom, ufr_id, filiere_id, niveau) VALUES (?,?,?,?,?,?)",
                        [$carte, $nom, $prenom, $ufrId, $filiereId, $niveau]);
                    $added++;
                }
            } catch (Exception $e) { $errors++; }
        }
        logAction('import_etudiants', "Import: +$added mis à jour:$updated erreurs:$errors", null, $admin['id']);
        jsonOut(['success'=>true,'added'=>$added,'updated'=>$updated,'errors'=>$errors]);
    }

    if ($action === 'toggle_etudiant') {
        requireSuperAdmin();
        $id = (int)($data['id'] ?? 0);
        $u = queryOne("SELECT * FROM utilisateurs WHERE id=?", [$id]);
        if (!$u) jsonOut(['success'=>false,'message'=>'Introuvable']);
        execute("UPDATE utilisateurs SET is_active=? WHERE id=?", [$u['is_active'] ? 0 : 1, $id]);
        jsonOut(['success'=>true,'message'=>$u['is_active'] ? 'Compte désactivé' : 'Compte activé']);
    }

    if ($action === 'traiter_demande') {
        requireSuperAdmin();
        $id = (int)($data['id'] ?? 0);
        $decision = $data['decision'] ?? '';
        $motif = sanitize($data['motif'] ?? '');
        $demande = queryOne("SELECT * FROM demandes_profil WHERE id=?", [$id]);
        if (!$demande) jsonOut(['success'=>false,'message'=>'Demande introuvable']);

        if ($decision === 'approuver') {
            execute("UPDATE utilisateurs SET ufr_id=?, filiere_id=?, niveau=? WHERE id=?",
                [$demande['ufr_id'], $demande['filiere_id'], $demande['niveau'], $demande['utilisateur_id']]);
            execute("UPDATE demandes_profil SET statut='approuvee' WHERE id=?", [$id]);
            notifyUser($demande['utilisateur_id'], 'Changement de profil approuvé', 'Votre demande de changement de profil a été approuvée.');
        } else {
            execute("UPDATE demandes_profil SET statut='refusee', motif_refus=? WHERE id=?", [$motif, $id]);
            notifyUser($demande['utilisateur_id'], 'Changement de profil refusé', "Votre demande a été refusée. Motif : $motif");
        }
        jsonOut(['success'=>true,'message'=>'Demande traitée']);
    }
}

// Upload logo universite
if ($method === 'POST' && isset($_FILES['logo'])) {
    requireSuperAdmin();
    if ($_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/webp','image/svg+xml'];
        if (!in_array($_FILES['logo']['type'], $allowed)) jsonOut(['success'=>false,'message'=>'Format invalide']);
        if ($_FILES['logo']['size'] > 2*1024*1024) jsonOut(['success'=>false,'message'=>'Logo max 2Mo']);
        $logoData = 'data:'.$_FILES['logo']['type'].';base64,'.base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
        execute("INSERT INTO settings (cle, valeur) VALUES ('universite_logo',?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)", [$logoData]);
        jsonOut(['success'=>true,'message'=>'Logo uploadé']);
    }
    jsonOut(['success'=>false,'message'=>'Erreur upload']);
}
?>
