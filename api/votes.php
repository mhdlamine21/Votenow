<?php
header('Content-Type: application/json; charset=utf-8');
// CORS handled per-origin - see .htaccess
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

secureSessionStart();
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'results';
    $electionId = (int)($_GET['election_id'] ?? 0);

    if ($action === 'results' && $electionId) {
        $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
        if (!$el) jsonOut(['success'=>false,'message'=>'Election introuvable']);

        $tour = (int)$el['tour'];
        $phase = $el['phase'];

        // Verifier si résultats visibles
        $canSeeResults = false;
        if (isset($_SESSION['admin_id'])) $canSeeResults = true;
        elseif ($el['resultats_publics'] === 'temps_reel' && in_array($phase, ['vote','vote_ferme','archivee'])) $canSeeResults = true;
        elseif ($el['resultats_publics'] === 'apres_cloture' && in_array($phase, ['vote_ferme','archivee'])) $canSeeResults = true;

        $totalVotants = queryOne("SELECT COUNT(*) as n FROM participations WHERE election_id=? AND tour=?", [$electionId, $tour])['n'];

        // Calcul éligibles
        $eligibles = countEligibles($el);

        if (!$canSeeResults) {
            jsonOut([
                'success' => true,
                'visible' => false,
                'total_votants' => (int)$totalVotants,
                'eligibles' => $eligibles,
                'message' => 'Les résultats seront disponibles après la clôture du vote.',
                'tour' => $tour,
                'phase' => $phase,
            ]);
        }

        // Résultats complets
        $where = $tour == 2 ? "AND c.qualifie_tour2 = TRUE" : "";
        $results = query("
            SELECT c.id, c.nom_complet, c.slogan, c.has_photo,
                   u.ufr_id, u.filiere_id, u.niveau,
                   uf.nom as ufr_nom, f.nom as filiere_nom,
                   COUNT(v.id) as nb_votes,
                   CASE WHEN c.photo IS NOT NULL THEN TRUE ELSE FALSE END as has_photo
            FROM candidatures c
            LEFT JOIN votes v ON v.candidature_id = c.id AND v.election_id = ? AND v.tour = ?
            LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id
            LEFT JOIN ufr uf ON u.ufr_id = uf.id
            LEFT JOIN filieres f ON u.filiere_id = f.id
            WHERE c.election_id = ? AND c.statut = 'validee' $where
            GROUP BY c.id
            ORDER BY nb_votes DESC", [$electionId, $tour, $electionId]);

        $maxVoix = count($results) > 0 ? (int)$results[0]['nb_votes'] : 0;
        $winners = $maxVoix > 0 ? array_filter($results, fn($r) => (int)$r['nb_votes'] === $maxVoix) : [];

        $formatted = array_map(function($r) use ($totalVotants) {
            return [
                'id' => $r['id'],
                'nom_complet' => $r['nom_complet'],
                'slogan' => $r['slogan'],
                'has_photo' => (bool)$r['has_photo'],
                'ufr_nom' => $r['ufr_nom'],
                'filiere_nom' => $r['filiere_nom'],
                'niveau' => $r['niveau'],
                'nb_votes' => (int)$r['nb_votes'],
                'pourcentage' => $totalVotants > 0 ? round($r['nb_votes'] / $totalVotants * 100, 1) : 0,
            ];
        }, $results);

        // Decision admin si ex-aequo tour 2
        $decision = null;
        if ($el['tour'] == 2 && in_array($phase, ['vote_ferme','archivee'])) {
            $decision = queryOne("SELECT da.*, c.nom_complet FROM decision_admin da JOIN candidatures c ON da.candidature_id=c.id WHERE da.election_id=?", [$electionId]);
        }

        jsonOut([
            'success' => true,
            'visible' => true,
            'total_votants' => (int)$totalVotants,
            'eligibles' => $eligibles,
            'taux_participation' => $eligibles > 0 ? round($totalVotants / $eligibles * 100, 1) : 0,
            'results' => array_values($formatted),
            'winners' => array_values($winners),
            'tour' => $tour,
            'phase' => $phase,
            'election' => $el,
            'decision_admin' => $decision,
        ]);
    }

    if ($action === 'check' && $electionId) {
        if (!isset($_SESSION['user_id'])) jsonOut(['voted'=>false,'eligible'=>false]);
        $user = queryOne("SELECT * FROM utilisateurs WHERE id=?", [$_SESSION['user_id']]);
        $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
        $eligible = $el ? isEligible($user, $el) : false;
        $voted = (bool)queryOne("SELECT id FROM participations WHERE election_id=? AND utilisateur_id=? AND tour=?", [$electionId, $user['id'], $el['tour'] ?? 1]);
        jsonOut(['voted'=>$voted,'eligible'=>$eligible,'tour'=>$el['tour'] ?? 1]);
    }

        // Export CSV votants
        if ($action === 'export_csv' && $electionId) {
            requireAdmin();
            $votants = query("
                SELECT u.carte_identite, u.nom, u.prenom,
                       uf.nom as ufr_nom, f.nom as filiere_nom, u.niveau,
                       p.tour, p.voted_at
                FROM participations p
                JOIN utilisateurs u ON p.utilisateur_id = u.id
                LEFT JOIN ufr uf ON u.ufr_id = uf.id
                LEFT JOIN filieres f ON u.filiere_id = f.id
                WHERE p.election_id = ?
                ORDER BY p.voted_at", [$electionId]);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="votants_election_'.$electionId.'.csv"');
            echo "\xEF\xBB\xBF"; // BOM UTF-8
            echo "Carte,Nom,Prenom,UFR,Filiere,Niveau,Tour,Date vote\n";
            foreach ($votants as $v) {
                echo implode(',', array_map(fn($x) => '"'.str_replace('"','""',$x).'"', [
                    $v['carte_identite'], $v['nom'], $v['prenom'],
                    $v['ufr_nom'], $v['filiere_nom'], $v['niveau'],
                    $v['tour'], $v['voted_at']
                ])) . "\n";
            }
            exit();
        }

        // Export PDF / Proces-verbal imprimable
        if ($action === 'export_pdf' && $electionId) {
            $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
            if (!$el) die("Election introuvable");
            $tour = (int)$el['tour'];
            $totalVotants = queryOne("SELECT COUNT(*) as n FROM participations WHERE election_id=? AND tour=?", [$electionId, $tour])['n'];
            $eligibles = countEligibles($el);
            $taux = $eligibles > 0 ? round($totalVotants / $eligibles * 100, 1) : 0;
            $results = query("
                SELECT c.nom_complet, c.slogan, COUNT(v.id) as nb_votes
                FROM candidatures c
                LEFT JOIN votes v ON v.candidature_id = c.id AND v.election_id = ? AND v.tour = ?
                WHERE c.election_id = ? AND c.statut = 'validee'
                GROUP BY c.id
                ORDER BY nb_votes DESC", [$electionId, $tour, $electionId]);

            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <title>PV Résultats - <?= htmlspecialchars($el['titre']) ?></title>
                <style>
                    body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 40px; color: #1e293b; line-height: 1.5; }
                    .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 20px; margin-bottom: 30px; }
                    .header h1 { margin: 0 0 5px 0; font-size: 24px; }
                    .meta { display: flex; justify-content: space-between; background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                    th { background: #f1f5f9; font-weight: 600; font-size: 13px; text-transform: uppercase; }
                    .winner { font-weight: bold; color: #15803d; }
                    .footer { margin-top: 50px; display: flex; justify-content: space-between; }
                    .sig { border-top: 1px dashed #94a3b8; width: 220px; padding-top: 8px; text-align: center; font-size: 12px; }
                    @media print { .no-print { display: none; } body { padding: 0; } }
                </style>
            </head>
            <body>
                <div class="no-print" style="margin-bottom:20px; text-align:right;">
                    <button onclick="window.print()" style="padding:10px 18px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
                        Imprimer / Enregistrer en PDF
                    </button>
                </div>
                <div class="header">
                    <h2>PROCES-VERBAL DU SCRUTIN</h2>
                    <h1><?= htmlspecialchars($el['titre']) ?></h1>
                    <p>Date d'extraction : <?= date('d/m/Y H:i:s') ?> · Tour <?= $tour ?></p>
                </div>
                <div class="meta">
                    <div><strong>Inscrits / Eligibles :</strong> <?= $eligibles ?></div>
                    <div><strong>Nombre de Votants :</strong> <?= $totalVotants ?></div>
                    <div><strong>Taux de Participation :</strong> <?= $taux ?> %</div>
                </div>
                <h3>Classement des suffrages exprimés</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Candidat</th>
                            <th>Suffrages (Voix)</th>
                            <th>Pourcentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r):
                            $pct = $totalVotants > 0 ? round($r['nb_votes'] / $totalVotants * 100, 2) : 0;
                        ?>
                        <tr class="<?= $i === 0 && $totalVotants > 0 ? 'winner' : '' ?>">
                            <td>#<?= $i + 1 ?> <?= $i === 0 && $totalVotants > 0 ? '🏆' : '' ?></td>
                            <td><?= htmlspecialchars($r['nom_complet']) ?></td>
                            <td><?= $r['nb_votes'] ?></td>
                            <td><?= $pct ?> %</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="footer">
                    <div class="sig">Le Président du Bureau de Vote</div>
                    <div class="sig">Le Superviseur Electoral</div>
                </div>
                <script>
                    window.onload = function() {
                        setTimeout(function() { window.print(); }, 400);
                    };
                </script>
            </body>
            </html>
            <?php
            exit();
        }
}

if ($method === 'POST') {
    $user = requireAuth();
    if (isset($_SESSION['is_admin'])) jsonOut(['success'=>false,'message'=>'Les admins ne peuvent pas voter']);

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }
    $electionId = (int)($data['election_id'] ?? 0);
    $candidatureId = (int)($data['candidature_id'] ?? 0);

    if (!$electionId || !$candidatureId) jsonOut(['success'=>false,'message'=>'Données manquantes']);

    $el = queryOne("SELECT * FROM elections WHERE id=?", [$electionId]);
    if (!$el || $el['phase'] !== 'vote') jsonOut(['success'=>false,'message'=>'Le vote est fermé']);

    $userFull = queryOne("SELECT * FROM utilisateurs WHERE id=?", [$user['id'] ?? $_SESSION['user_id']]);
    if (!isEligible($userFull, $el)) jsonOut(['success'=>false,'message'=>'Vous n\'êtes pas éligible à cette élection']);

    $tour = (int)$el['tour'];
    $userId = $_SESSION['user_id'];

    $alreadyVoted = queryOne("SELECT id FROM participations WHERE election_id=? AND utilisateur_id=? AND tour=?", [$electionId, $userId, $tour]);
    if ($alreadyVoted) jsonOut(['success'=>false,'message'=>'Vous avez déjà voté pour cette élection']);

    // Verifier candidature valide
    $cand = queryOne("SELECT * FROM candidatures WHERE id=? AND election_id=? AND statut='validee'", [$candidatureId, $electionId]);
    if (!$cand) jsonOut(['success'=>false,'message'=>'Candidat invalide']);
    if ($tour == 2 && !$cand['qualifie_tour2']) jsonOut(['success'=>false,'message'=>'Candidat non qualifié pour le 2ème tour']);

    db()->beginTransaction();
    try {
        // Vote anonyme - pas de lien direct utilisateur → candidature
        execute("INSERT INTO votes (election_id, candidature_id, tour) VALUES (?,?,?)", [$electionId, $candidatureId, $tour]);
        // Participation (qui a voté, pas pour qui)
        execute("INSERT INTO participations (election_id, utilisateur_id, tour) VALUES (?,?,?)", [$electionId, $userId, $tour]);
        db()->commit();
        logAction('vote', "Vote enregistré election #{$electionId} tour $tour", $userId);
        jsonOut(['success'=>true,'message'=>'Vote enregistré avec succès !']);
    } catch (Exception $e) {
        db()->rollBack();
        jsonOut(['success'=>false,'message'=>'Erreur lors du vote. Réessayez.']);
    }
}

if ($method === 'DELETE') {
    $admin = requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $electionId = (int)($data['election_id'] ?? 0);
    if (!$electionId) jsonOut(['success'=>false,'message'=>'election_id requis']);

    db()->beginTransaction();
    try {
        execute("DELETE FROM votes WHERE election_id=?", [$electionId]);
        execute("DELETE FROM participations WHERE election_id=?", [$electionId]);
        execute("UPDATE elections SET tour=1 WHERE id=?", [$electionId]);
        execute("UPDATE candidatures SET qualifie_tour2=FALSE WHERE election_id=?", [$electionId]);
        db()->commit();
        logAction('reset_votes', "Votes réinitialisés election #{$electionId}", null, $admin['id']);
        jsonOut(['success'=>true,'message'=>'Votes réinitialisés']);
    } catch (Exception $e) {
        db()->rollBack();
        jsonOut(['success'=>false,'message'=>'Erreur lors de la réinitialisation']);
    }
}

function countEligibles(array $el): int {
    if ($el['scope'] === 'universite') return (int)queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE is_active=1")['n'];
    if ($el['scope'] === 'ufr') return (int)queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE ufr_id=? AND is_active=1", [$el['ufr_id']])['n'];
    if ($el['scope'] === 'filiere') return (int)queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE filiere_id=? AND is_active=1", [$el['filiere_id']])['n'];
    if ($el['scope'] === 'niveau') return (int)queryOne("SELECT COUNT(*) as n FROM utilisateurs WHERE filiere_id=? AND niveau=? AND is_active=1", [$el['filiere_id'], $el['niveau']])['n'];
    return 0;
}
?>
