<?php
header('Content-Type: application/json; charset=utf-8');
// CORS handled per-origin - see .htaccess
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

secureSessionStart();
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($id) {
        $el = queryOne("
            SELECT e.*, a.username as admin_nom,
                   u.nom as ufr_nom, f.nom as filiere_nom
            FROM elections e
            LEFT JOIN admins a ON e.admin_id = a.id
            LEFT JOIN ufr u ON e.ufr_id = u.id
            LEFT JOIN filieres f ON e.filiere_id = f.id
            WHERE e.id = ?", [$id]);
        if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable'], 404);

        $el['nb_candidatures'] = queryOne("SELECT COUNT(*) as n FROM candidatures WHERE election_id=? AND statut='validee'", [$id])['n'];
        $el['nb_votes'] = queryOne("SELECT COUNT(*) as n FROM participations WHERE election_id=?", [$id])['n'];

        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = queryOne("SELECT * FROM utilisateurs WHERE id=?", [$_SESSION['user_id']]);
            $el['eligible'] = isEligible($user, $el);
            $el['user_voted_t1'] = (bool)queryOne("SELECT id FROM participations WHERE election_id=? AND utilisateur_id=? AND tour=1", [$id, $_SESSION['user_id']]);
            $el['user_voted_t2'] = (bool)queryOne("SELECT id FROM participations WHERE election_id=? AND utilisateur_id=? AND tour=2", [$id, $_SESSION['user_id']]);
            $el['user_candidature'] = queryOne("SELECT * FROM candidatures WHERE election_id=? AND utilisateur_id=?", [$id, $_SESSION['user_id']]);
        }
        jsonOut(['success'=>true,'election'=>$el]);
    }

    // Liste des elections
    $where = ['1=1'];
    $params = [];
    $phase = $_GET['phase'] ?? null;
    if ($phase) { $where[] = 'e.phase = ?'; $params[] = $phase; }

    $elections = query("
        SELECT e.*, u.nom as ufr_nom, f.nom as filiere_nom,
               COUNT(DISTINCT c.id) as nb_candidatures,
               COUNT(DISTINCT p.id) as nb_votes
        FROM elections e
        LEFT JOIN ufr u ON e.ufr_id = u.id
        LEFT JOIN filieres f ON e.filiere_id = f.id
        LEFT JOIN candidatures c ON c.election_id = e.id AND c.statut = 'validee'
        LEFT JOIN participations p ON p.election_id = e.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY e.id
        ORDER BY e.created_at DESC", $params);

    if (isset($_SESSION['user_id'])) {
        $user = queryOne("SELECT * FROM utilisateurs WHERE id=?", [$_SESSION['user_id']]);
        foreach ($elections as &$el) {
            $el['eligible'] = isEligible($user, $el);
            $el['user_voted'] = (bool)queryOne(
                "SELECT id FROM participations WHERE election_id=? AND utilisateur_id=? AND tour=?",
                [$el['id'], $_SESSION['user_id'], $el['tour']]
            );
        }
        unset($el);
    }
    jsonOut(['success'=>true,'elections'=>$elections]);
}

if ($method === 'POST') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? 'create';

    if ($action === 'phase') {
        $id = (int)($data['id'] ?? 0);
        $newPhase = $data['phase'] ?? '';
        $phases = ['brouillon','candidatures','candidatures_fermees','vote','vote_ferme','archivee'];
        if (!in_array($newPhase, $phases)) jsonOut(['success'=>false,'message'=>'Phase invalide']);

        $el = queryOne("SELECT * FROM elections WHERE id=?", [$id]);
        if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable']);

        // Verifier permissions admin
        if ($admin['role'] !== 'superadmin') {
            if ($admin['perimetre'] === 'ufr' && (int)$el['ufr_id'] !== (int)$admin['ufr_id']) {
                jsonOut(['success'=>false,'message'=>'Accès refusé à cette élection']);
            }
        }

        execute("UPDATE elections SET phase=? WHERE id=?", [$newPhase, $id]);

        if ($newPhase === 'archivee') {
            execute("UPDATE elections SET archived_at=NOW() WHERE id=?", [$id]);
        }

        // Notifications aux etudiants eligibles
        $msgs = [
            'candidatures' => ['Candidatures ouvertes', "L'élection \"{$el['titre']}\" est ouverte aux candidatures."],
            'vote' => ['Vote ouvert', "Votez maintenant pour l'élection \"{$el['titre']}\"."],
            'vote_ferme' => ['Résultats disponibles', "Les résultats de l'élection \"{$el['titre']}\" sont disponibles."],
        ];
        if (isset($msgs[$newPhase])) {
            notifyEligibles($id, $el, $msgs[$newPhase][0], $msgs[$newPhase][1]);
        }

        logAction('phase_change', "Election #{$id} → {$newPhase}", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Phase mise à jour']);
    }

    if ($action === 'tour2') {
        $id = (int)($data['id'] ?? 0);
        $el = queryOne("SELECT * FROM elections WHERE id=?", [$id]);
        if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable']);
        if ($el['tour'] != 1) jsonOut(['success'=>false,'message'=>'Déjà en 2ème tour']);

        $results = query("
            SELECT c.id, COUNT(v.id) as voix
            FROM candidatures c
            LEFT JOIN votes v ON v.candidature_id=c.id AND v.election_id=? AND v.tour=1
            WHERE c.election_id=? AND c.statut='validee'
            GROUP BY c.id ORDER BY voix DESC", [$id, $id]);

        if (count($results) < 2) jsonOut(['success'=>false,'message'=>'Pas assez de candidats']);
        $maxVoix = (int)$results[0]['voix'];
        $secondVoix = (int)$results[1]['voix'];
        if ($maxVoix !== $secondVoix) jsonOut(['success'=>false,'message'=>'Aucune égalité détectée. Pas besoin d\'un 2ème tour.']);

        execute("UPDATE candidatures SET qualifie_tour2=FALSE WHERE election_id=?", [$id]);
        foreach ($results as $r) {
            if ((int)$r['voix'] === $maxVoix) {
                execute("UPDATE candidatures SET qualifie_tour2=TRUE WHERE id=?", [$r['id']]);
            }
        }
        execute("UPDATE elections SET tour=2, phase='vote' WHERE id=?", [$id]);
        execute("DELETE FROM participations WHERE election_id=? AND tour=2", [$id]);
        logAction('tour2', "2ème tour lancé pour election #{$id}", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'2ème tour lancé avec succès']);
    }

    if ($action === 'decision') {
        $id = (int)($data['election_id'] ?? 0);
        $candidatureId = (int)($data['candidature_id'] ?? 0);
        $motif = sanitize($data['motif'] ?? '');
        if (!$motif) jsonOut(['success'=>false,'message'=>'Le motif est obligatoire']);

        execute("INSERT INTO decision_admin (election_id, candidature_id, motif, admin_id) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE candidature_id=VALUES(candidature_id), motif=VALUES(motif), decided_at=NOW()",
            [$id, $candidatureId, $motif, $admin['id']]);
        execute("UPDATE elections SET phase='vote_ferme' WHERE id=?", [$id]);
        logAction('decision_admin', "Décision admin election #{$id} → candidature #{$candidatureId}", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Décision enregistrée']);
    }

    // Créer election
    $titre = sanitize($data['titre'] ?? '');
    if (!$titre) jsonOut(['success'=>false,'message'=>'Le titre est obligatoire']);

    $scope = $data['scope'] ?? 'universite';
    $ufrId = !empty($data['ufr_id']) ? (int)$data['ufr_id'] : null;
    $filiereId = !empty($data['filiere_id']) ? (int)$data['filiere_id'] : null;
    $niveau = !empty($data['niveau']) ? sanitize($data['niveau']) : null;

    // Verifier perimetre admin
    if ($admin['role'] !== 'superadmin') {
        if ($admin['perimetre'] === 'ufr') {
            if ($scope === 'universite') jsonOut(['success'=>false,'message'=>'Votre périmètre ne permet pas une élection universitaire']);
            $ufrId = $admin['ufr_id'];
        }
        if ($admin['perimetre'] === 'filiere') {
            if (in_array($scope, ['universite','ufr'])) jsonOut(['success'=>false,'message'=>'Périmètre insuffisant']);
            $filiereId = $admin['filiere_id'];
        }
    }

    execute("INSERT INTO elections (admin_id, titre, description, scope, ufr_id, filiere_id, niveau, resultats_publics, date_candidatures_debut, date_candidatures_fin, date_vote_debut, date_vote_fin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $admin['id'], $titre,
            sanitize($data['description'] ?? ''),
            $scope, $ufrId, $filiereId, $niveau,
            $data['resultats_publics'] ?? 'apres_cloture',
            $data['date_candidatures_debut'] ?: null,
            $data['date_candidatures_fin'] ?: null,
            $data['date_vote_debut'] ?: null,
            $data['date_vote_fin'] ?: null,
        ]
    );
    logAction('create_election', "Election créée: $titre", null, $admin['id']);
    jsonOut(['success'=>true,'message'=>'Élection créée','id'=>lastId()]);
}

if ($method === 'PUT') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    $el = queryOne("SELECT * FROM elections WHERE id=?", [$id]);
    if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable']);

    execute("UPDATE elections SET titre=?, description=?, date_candidatures_debut=?, date_candidatures_fin=?, date_vote_debut=?, date_vote_fin=?, resultats_publics=? WHERE id=?",
        [
            sanitize($data['titre'] ?? $el['titre']),
            sanitize($data['description'] ?? $el['description']),
            $data['date_candidatures_debut'] ?: null,
            $data['date_candidatures_fin'] ?: null,
            $data['date_vote_debut'] ?: null,
            $data['date_vote_fin'] ?: null,
            $data['resultats_publics'] ?? $el['resultats_publics'],
            $id
        ]
    );
    jsonOut(['success'=>true,'message'=>'Élection mise à jour']);
}

if ($method === 'DELETE') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    execute("DELETE FROM elections WHERE id=?", [$id]);
    logAction('delete_election', "Election #{$id} supprimée", null, $admin['id']);
    jsonOut(['success'=>true,'message'=>'Élection supprimée']);
}

function notifyEligibles(int $electionId, array $el, string $titre, string $msg): void {
    $users = [];
    if ($el['scope'] === 'universite') {
        $users = query("SELECT id FROM utilisateurs WHERE is_active=1");
    } elseif ($el['scope'] === 'ufr') {
        $users = query("SELECT id FROM utilisateurs WHERE ufr_id=? AND is_active=1", [$el['ufr_id']]);
    } elseif ($el['scope'] === 'filiere') {
        $users = query("SELECT id FROM utilisateurs WHERE filiere_id=? AND is_active=1", [$el['filiere_id']]);
    } elseif ($el['scope'] === 'niveau') {
        $users = query("SELECT id FROM utilisateurs WHERE filiere_id=? AND niveau=? AND is_active=1", [$el['filiere_id'], $el['niveau']]);
    }
    foreach ($users as $u) {
        notifyUser((int)$u['id'], $titre, $msg, "election={$electionId}");
    }
}
?>
