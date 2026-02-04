<?php
header('Content-Type: application/json; charset=utf-8');
// CORS handled per-origin - see .htaccess
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

secureSessionStart();
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$section = $_GET['section'] ?? 'notifications';

if ($method === 'GET') {
    if ($section === 'notifications') {
        $user = requireAuth();
        $userId = $_SESSION['user_id'];
        $notifs = query("SELECT * FROM notifications WHERE utilisateur_id=? ORDER BY created_at DESC LIMIT 50", [$userId]);
        $unread = queryOne("SELECT COUNT(*) as n FROM notifications WHERE utilisateur_id=? AND lue=0", [$userId])['n'];
        jsonOut(['success'=>true,'notifications'=>$notifs,'unread'=>(int)$unread]);
    }

    if ($section === 'profil') {
        $user = requireAuth();
        $userId = $_SESSION['user_id'];
        $profil = queryOne("SELECT u.*, uf.nom as ufr_nom, f.nom as filiere_nom FROM utilisateurs u LEFT JOIN ufr uf ON u.ufr_id=uf.id LEFT JOIN filieres f ON u.filiere_id=f.id WHERE u.id=?", [$userId]);
        $demande = queryOne("SELECT d.*, uf.nom as ufr_nom, f.nom as filiere_nom FROM demandes_profil d LEFT JOIN ufr uf ON d.ufr_id=uf.id LEFT JOIN filieres f ON d.filiere_id=f.id WHERE d.utilisateur_id=? AND d.statut='en_attente' ORDER BY d.created_at DESC LIMIT 1", [$userId]);
        $candidatures = query("SELECT c.*, e.titre as election_titre FROM candidatures c JOIN elections e ON c.election_id=e.id WHERE c.utilisateur_id=? ORDER BY c.created_at DESC", [$userId]);
        jsonOut(['success'=>true,'profil'=>$profil,'demande_en_cours'=>$demande,'candidatures'=>$candidatures]);
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $user = requireAuth();
        $userId = $_SESSION['user_id'];
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            execute("UPDATE notifications SET lue=1 WHERE id=? AND utilisateur_id=?", [$id, $userId]);
        } else {
            execute("UPDATE notifications SET lue=1 WHERE utilisateur_id=?", [$userId]);
        }
        jsonOut(['success'=>true]);
    }

    if ($action === 'demande_profil') {
        $user = requireAuth();
        $userId = $_SESSION['user_id'];
        $ufrId = (int)($data['ufr_id'] ?? 0);
        $filiereId = (int)($data['filiere_id'] ?? 0);
        $niveau = sanitize($data['niveau'] ?? '');

        if (!$ufrId || !$filiereId || !$niveau) jsonOut(['success'=>false,'message'=>'Tous les champs sont requis']);

        // Verifier pas d'elections actives ou bloquer
        $electionsActives = query("
            SELECT e.id FROM elections e
            JOIN participations p ON p.election_id=e.id AND p.utilisateur_id=?
            WHERE e.phase='vote'", [$userId]);

        if (count($electionsActives) > 0) {
            jsonOut(['success'=>false,'message'=>'Modification impossible pendant un vote actif où vous avez déjà voté']);
        }

        $demandeExiste = queryOne("SELECT id FROM demandes_profil WHERE utilisateur_id=? AND statut='en_attente'", [$userId]);
        if ($demandeExiste) jsonOut(['success'=>false,'message'=>'Une demande est déjà en cours']);

        execute("INSERT INTO demandes_profil (utilisateur_id, ufr_id, filiere_id, niveau) VALUES (?,?,?,?)",
            [$userId, $ufrId, $filiereId, $niveau]);
        logAction('demande_profil', "Demande changement profil", $userId);
        jsonOut(['success'=>true,'message'=>'Demande envoyée. En attente de validation par l\'administration.']);
    }

    if ($action === 'change_password') {
        $user = requireAuth();
        $userId = $_SESSION['user_id'];
        $oldPwd = $data['old_password'] ?? '';
        $newPwd = $data['new_password'] ?? '';
        if (strlen($newPwd) < 6) jsonOut(['success'=>false,'message'=>'Nouveau mot de passe minimum 6 caractères']);
        $u = queryOne("SELECT password_hash FROM utilisateurs WHERE id=?", [$userId]);
        if (!password_verify($oldPwd, $u['password_hash'])) jsonOut(['success'=>false,'message'=>'Mot de passe actuel incorrect']);
        execute("UPDATE utilisateurs SET password_hash=? WHERE id=?", [password_hash($newPwd, PASSWORD_DEFAULT), $userId]);
        logAction('change_password', 'Mot de passe changé', $userId);
        jsonOut(['success'=>true,'message'=>'Mot de passe modifié avec succès']);
    }
}
?>
