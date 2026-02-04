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
    $electionId = (int)($_GET['election_id'] ?? 0);
    $id = (int)($_GET['id'] ?? 0);

    if ($id) {
        $c = queryOne("
            SELECT c.*, u.ufr_id, u.filiere_id, u.niveau,
                   uf.nom as ufr_nom, f.nom as filiere_nom
            FROM candidatures c
            JOIN utilisateurs u ON c.utilisateur_id = u.id
            LEFT JOIN ufr uf ON u.ufr_id = uf.id
            LEFT JOIN filieres f ON u.filiere_id = f.id
            WHERE c.id = ?", [$id]);

        // Masquer la photo si non connecté (visiteur)
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
            unset($c['photo'], $c['programme'], $c['description']);
        }
        jsonOut(['success'=>true,'candidature'=>$c]);
    }

    if (!$electionId) jsonOut(['success'=>false,'message'=>'election_id requis']);

    $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
    if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable']);

    // Statut à afficher
    $statutFilter = [];
    $params = [$electionId];
    $tour2Only = isset($_GET['tour2']) && $_GET['tour2'] == '1';

    if (isset($_SESSION['admin_id'])) {
        // Admin voit tout
        $statutFilter = [];
    } else {
        // Public voit seulement les validées
        $statutFilter = ["c.statut = 'validee'"];
    }

    if ($tour2Only) $statutFilter[] = "c.qualifie_tour2 = TRUE";

    $where = $statutFilter ? "AND " . implode(' AND ', $statutFilter) : '';

    $candidatures = query("
        SELECT c.id, c.nom_complet, c.slogan, c.statut, c.qualifie_tour2,
               c.utilisateur_id, c.created_at, c.motif_refus,
               CASE WHEN c.photo IS NOT NULL THEN TRUE ELSE FALSE END as has_photo,
               CASE WHEN c.programme IS NOT NULL THEN TRUE ELSE FALSE END as has_programme,
               u.ufr_id, u.filiere_id, u.niveau,
               uf.nom as ufr_nom, uf.code as ufr_code, f.nom as filiere_nom
        FROM candidatures c
        JOIN utilisateurs u ON c.utilisateur_id = u.id
        LEFT JOIN ufr uf ON u.ufr_id = uf.id
        LEFT JOIN filieres f ON u.filiere_id = f.id
        WHERE c.election_id = ? $where
        ORDER BY c.created_at ASC", $params);

    // Ajouter nb_votes par candidature pour les résultats
    $tour = (int)($el['tour'] ?? 1);
    foreach ($candidatures as &$c) {
        $v = queryOne("SELECT COUNT(*) as n FROM votes WHERE candidature_id=? AND election_id=? AND tour=?", [$c['id'], $electionId, $tour]);
        $c['nb_votes'] = (int)$v['n'];
    }
    unset($c);

    jsonOut(['success'=>true,'candidatures'=>$candidatures,'tour'=>$tour]);
}

if ($method === 'POST') {
    // Admin ajoute manuellement
    if (isset($_SESSION['admin_id'])) {
        $admin = requireAdmin();
        $electionId = (int)($_POST['election_id'] ?? 0);
        $userId = (int)($_POST['utilisateur_id'] ?? 0);
        $nomComplet = sanitize($_POST['nom_complet'] ?? '');
        $slogan = sanitize($_POST['slogan'] ?? '');
        $description = sanitize($_POST['description'] ?? '');

        if (!$electionId || !$nomComplet) jsonOut(['success'=>false,'message'=>'Champs obligatoires manquants']);

        $photo = null;
        $programme = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (in_array($_FILES['photo']['type'], $allowed) && $_FILES['photo']['size'] <= 5*1024*1024) {
                $photo = 'data:'.$_FILES['photo']['type'].';base64,'.base64_encode(file_get_contents($_FILES['photo']['tmp_name']));
            }
        }
        if (isset($_FILES['programme']) && $_FILES['programme']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['programme']['type'] === 'application/pdf' && $_FILES['programme']['size'] <= 10*1024*1024) {
                $programme = 'data:application/pdf;base64,'.base64_encode(file_get_contents($_FILES['programme']['tmp_name']));
            }
        }

        execute("INSERT INTO candidatures (election_id, utilisateur_id, nom_complet, slogan, description, photo, programme, statut) VALUES (?,?,?,?,?,?,?,'validee')",
            [$electionId, $userId ?: null, $nomComplet, $slogan, $description, $photo, $programme]);
        logAction('add_candidat', "Candidat ajouté: $nomComplet à election #$electionId", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Candidat ajouté','id'=>lastId()]);
    }

    // Étudiant dépose sa candidature
    $user = requireAuth();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $electionId = (int)($data['election_id'] ?? 0);

    $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
    if (!$el || $el['phase'] !== 'candidatures') {
        jsonOut(['success'=>false,'message'=>'Les candidatures ne sont pas ouvertes']);
    }
    if (!isEligible($user, $el)) jsonOut(['success'=>false,'message'=>'Vous n\'êtes pas éligible à cette élection']);

    $exists = queryOne("SELECT id FROM candidatures WHERE election_id=? AND utilisateur_id=?", [$electionId, $user['id']]);
    if ($exists) jsonOut(['success'=>false,'message'=>'Vous avez déjà déposé une candidature pour cette élection']);

    $step = $data['step'] ?? 'submit';

    if ($step === 'submit') {
        $nomComplet = sanitize($data['nom_complet'] ?? '');
        $slogan = sanitize($data['slogan'] ?? '');
        $description = sanitize($data['description'] ?? '');
        if (!$nomComplet || !$slogan) jsonOut(['success'=>false,'message'=>'Nom et slogan obligatoires']);
        if (strlen($slogan) > 150) jsonOut(['success'=>false,'message'=>'Slogan max 150 caractères']);
        if (strlen($description) > 1000) jsonOut(['success'=>false,'message'=>'Description max 1000 caractères']);

        execute("INSERT INTO candidatures (election_id, utilisateur_id, nom_complet, slogan, description) VALUES (?,?,?,?,?)",
            [$electionId, $user['id'], $nomComplet, $slogan, $description]);
        $cId = lastId();

        notifyAdminsNewCandidature($electionId, $el['titre'], $nomComplet);
        logAction('candidature', "Candidature déposée pour election #$electionId", $user['id']);
        jsonOut(['success'=>true,'message'=>'Candidature soumise. En attente de validation.','id'=>$cId]);
    }
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    $action = $data['action'] ?? '';

    // Admin valide/refuse
    if (isset($_SESSION['admin_id'])) {
        $admin = requireAdmin();
        if ($action === 'valider') {
            execute("UPDATE candidatures SET statut='validee' WHERE id=?", [$id]);
            $c = queryOne("SELECT c.*, e.titre FROM candidatures c JOIN elections e ON c.election_id=e.id WHERE c.id=?", [$id]);
            if ($c && $c['utilisateur_id']) {
                notifyUser($c['utilisateur_id'], 'Candidature validée', "Votre candidature pour \"{$c['titre']}\" a été validée.", "election={$c['election_id']}");
            }
            logAction('valider_candidature', "Candidature #$id validée", null, $admin['id']);
            jsonOut(['success'=>true,'message'=>'Candidature validée']);
        }
        if ($action === 'refuser') {
            $motif = sanitize($data['motif'] ?? '');
            if (!$motif) jsonOut(['success'=>false,'message'=>'Le motif de refus est obligatoire']);
            execute("UPDATE candidatures SET statut='refusee', motif_refus=? WHERE id=?", [$motif, $id]);
            $c = queryOne("SELECT c.*, e.titre FROM candidatures c JOIN elections e ON c.election_id=e.id WHERE c.id=?", [$id]);
            if ($c && $c['utilisateur_id']) {
                notifyUser($c['utilisateur_id'], 'Candidature refusée', "Votre candidature pour \"{$c['titre']}\" a été refusée. Motif : $motif", "election={$c['election_id']}");
            }
            logAction('refuser_candidature', "Candidature #$id refusée: $motif", null, $admin['id']);
            jsonOut(['success'=>true,'message'=>'Candidature refusée']);
        }
        jsonOut(['success'=>false,'message'=>'Action inconnue']);
    }

    // Étudiant modifie ou retire
    $user = requireAuth();
    $c = queryOne("SELECT c.*, e.phase FROM candidatures c JOIN elections e ON c.election_id=e.id WHERE c.id=? AND c.utilisateur_id=?", [$id, $user['id']]);
    if (!$c) jsonOut(['success'=>false,'message'=>'Candidature introuvable']);

    if ($action === 'retirer') {
        if ($c['phase'] !== 'candidatures') jsonOut(['success'=>false,'message'=>'Retrait impossible dans cette phase']);
        execute("UPDATE candidatures SET statut='retiree' WHERE id=?", [$id]);
        logAction('retrait_candidature', "Candidature #$id retirée", $user['id']);
        jsonOut(['success'=>true,'message'=>'Candidature retirée']);
    }

    if ($action === 'modifier') {
        if (!in_array($c['phase'], ['candidatures'])) jsonOut(['success'=>false,'message'=>'Modification impossible dans cette phase']);
        $nomComplet = sanitize($data['nom_complet'] ?? $c['nom_complet']);
        $slogan = sanitize($data['slogan'] ?? $c['slogan']);
        $description = sanitize($data['description'] ?? $c['description']);
        execute("UPDATE candidatures SET nom_complet=?, slogan=?, description=?, statut='en_attente' WHERE id=?",
            [$nomComplet, $slogan, $description, $id]);
        logAction('modifier_candidature', "Candidature #$id modifiée", $user['id']);
        jsonOut(['success'=>true,'message'=>'Candidature modifiée. En attente de revalidation.']);
    }
}

if ($method === 'DELETE') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    execute("DELETE FROM candidatures WHERE id=?", [$id]);
    logAction('delete_candidat', "Candidature #$id supprimée", null, $admin['id']);
    jsonOut(['success'=>true,'message'=>'Candidat supprimé']);
}

// Upload photo/programme séparé (multipart)
if ($method === 'POST' && isset($_FILES['photo']) || isset($_FILES['programme'])) {
    $user = requireAuth();
    $id = (int)($_POST['id'] ?? 0);
    $c = queryOne("SELECT * FROM candidatures WHERE id=? AND utilisateur_id=?", [$id, $user['id']]);
    if (!$c) jsonOut(['success'=>false,'message'=>'Candidature introuvable']);

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($_FILES['photo']['type'], $allowed)) jsonOut(['success'=>false,'message'=>'Format photo invalide (JPG, PNG, WEBP)']);
        if ($_FILES['photo']['size'] > 5*1024*1024) jsonOut(['success'=>false,'message'=>'Photo trop lourde (max 5Mo)']);
        $photoData = 'data:'.$_FILES['photo']['type'].';base64,'.base64_encode(file_get_contents($_FILES['photo']['tmp_name']));
        execute("UPDATE candidatures SET photo=? WHERE id=?", [$photoData, $id]);
    }
    if (isset($_FILES['programme']) && $_FILES['programme']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['programme']['type'] !== 'application/pdf') jsonOut(['success'=>false,'message'=>'Format programme invalide (PDF uniquement)']);
        if ($_FILES['programme']['size'] > 10*1024*1024) jsonOut(['success'=>false,'message'=>'PDF trop lourd (max 10Mo)']);
        $pdfData = 'data:application/pdf;base64,'.base64_encode(file_get_contents($_FILES['programme']['tmp_name']));
        execute("UPDATE candidatures SET programme=? WHERE id=?", [$pdfData, $id]);
    }
    jsonOut(['success'=>true,'message'=>'Fichiers uploadés']);
}

function notifyAdminsNewCandidature(int $electionId, string $titre, string $nomCandidат): void {
    $admins = query("SELECT id FROM admins WHERE is_active=1");
    foreach ($admins as $a) {
        execute("INSERT INTO notifications (utilisateur_id, titre, message, lien) SELECT u.id, ?, ?, ? FROM utilisateurs u LIMIT 0", []);
    }
}
?>
