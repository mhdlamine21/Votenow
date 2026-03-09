<?php
/**
 * commentaires.php - Module Démonstration Faille XSS (Cross-Site Scripting)
 * Cours : Introduction à la Sécurité (L2 S4 - 2025-2026)
 * 
 * Vulnérabilité démontrée lors de la soutenance :
 * Code vulnérable : <div><?php echo $cm['content']; ?></div>
 * Code corrigé   : <div><?php echo htmlspecialchars($cm['content'], ENT_QUOTES, 'UTF-8'); ?></div>
 */

require_once 'config/database.php';
secureSessionStart();

// Bascule mode sécurisé vs vulnérable via GET/POST
$isSecure = isset($_GET['secure']) && $_GET['secure'] === '1';

// Traitement nouveau commentaire
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auteur = trim($_POST['auteur'] ?? 'Électeur Anonyme');
    $content = trim($_POST['content'] ?? '');

    if (!empty($content)) {
        // Enregistrement brut dans la base de données (Stored XSS)
        execute(
            "INSERT INTO commentaires (election_id, auteur, content) VALUES (1, ?, ?)",
            [$auteur ?: 'Étudiant', $content]
        );
        $message = "Commentaire enregistré avec succès !";
    }
}

// Action de purge pour réinitialiser les tests
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    execute("DELETE FROM commentaires WHERE election_id = 1");
    // Réinsérer deux commentaires d'exemple
    execute("INSERT INTO commentaires (election_id, auteur, content) VALUES (1, 'Mamadou Sy', 'Très bonne organisation pour ce premier tour.')");
    execute("INSERT INTO commentaires (election_id, auteur, content) VALUES (1, 'Mouhamadou Lamine Niang', 'Le scrutin se déroule dans le calme.')");
    header('Location: commentaires.php' . ($isSecure ? '?secure=1' : ''));
    exit();
}

// Récupération des commentaires
$commentaires = query("SELECT * FROM commentaires WHERE election_id = 1 ORDER BY created_at DESC LIMIT 20");
if (empty($commentaires)) {
    execute("INSERT INTO commentaires (election_id, auteur, content) VALUES (1, 'Mamadou Sy', 'Très bonne organisation pour ce premier tour.')");
    execute("INSERT INTO commentaires (election_id, auteur, content) VALUES (1, 'Mouhamadou Lamine Niang', 'Le scrutin se déroule dans le calme.')");
    $commentaires = query("SELECT * FROM commentaires WHERE election_id = 1 ORDER BY created_at DESC LIMIT 20");
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démonstration Faille XSS - VoteNow</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .lab_container { max-width: 860px; margin: 30px auto; padding: 20px; }
        .mode_toggle {
            display: flex; gap: 12px; margin-bottom: 24px; padding: 16px;
            background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
            align-items: center; justify-content: space-between; flex-wrap: wrap;
        }
        .badge_vuln { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px; }
        .badge_secure { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.4); padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px; }
        .payload_btns { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
        .btn_payload { background: var(--bg-hover); border: 1px dashed var(--border); padding: 5px 10px; font-size: 12px; border-radius: 6px; cursor: pointer; color: var(--text-2); }
        .btn_payload:hover { border-color: var(--blue); color: var(--blue); }
        .comment_card {
            background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
            padding: 14px 18px; margin-bottom: 12px;
        }
        .comment_author { font-weight: 700; color: var(--text-1); font-size: 14px; margin-bottom: 4px; }
        .comment_date { font-size: 11px; color: var(--text-3); }
        .comment_body { margin-top: 8px; color: var(--text-2); font-size: 14px; line-height: 1.5; }
        .code_box { background: rgba(0,0,0,0.3); border-radius: 8px; padding: 12px; font-family: monospace; font-size: 12px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="lab_container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <a href="index.php" class="btn btn_ghost"><i class="fa-solid fa-arrow-left"></i> Retour à VoteNow</a>
            <a href="commentaires.php?reset=1<?= $isSecure ? '&secure=1' : '' ?>" class="btn btn_ghost btn_sm"><i class="fa-solid fa-rotate-right"></i> Réinitialiser les données</a>
        </div>

        <div class="hero" style="border-radius:14px; margin-bottom:24px; padding:24px;">
            <div class="hero_label"><i class="fa-solid fa-shield-halved"></i> Atelier Pratique Sécurité</div>
            <h1 style="font-size:1.6rem; margin-top:8px;">Démonstration - Faille <span>XSS (Cross-Site Scripting)</span></h1>
            <p style="color:rgba(255,255,255,0.7); font-size:14px; margin-top:6px;">
                Module : Introduction à la Sécurité (L2 S4) - Équipe : Mouhamadou Lamine Niang, Mamadou Sy, Papa Mangoné Gueye
            </p>
        </div>

        <!-- Bascule de mode -->
        <div class="mode_toggle">
            <div>
                <strong>Mode d'Affichage Actuel :</strong>
                <?php if ($isSecure): ?>
                    <span class="badge_secure"><i class="fa-solid fa-check"></i> Sécurisé (htmlspecialchars)</span>
                <?php else: ?>
                    <span class="badge_vuln"><i class="fa-solid fa-triangle-exclamation"></i> Vulnérable (Affichage Brut)</span>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($isSecure): ?>
                    <a href="commentaires.php" class="btn btn_outline btn_sm"><i class="fa-solid fa-bug"></i> Passer en Mode Vulnérable</a>
                <?php else: ?>
                    <a href="commentaires.php?secure=1" class="btn btn_primary btn_sm"><i class="fa-solid fa-shield"></i> Activer la Remédiation Sécurisée</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire de soumission -->
        <div class="card" style="margin-bottom:24px;">
            <h3 style="margin-bottom:14px; font-size:16px;"><i class="fa-regular fa-comment-dots"></i> Laisser un avis / commentaire</h3>
            
            <form method="POST" action="commentaires.php<?= $isSecure ? '?secure=1' : '' ?>">
                <div class="form_group">
                    <label class="form_label">Votre Nom</label>
                    <input type="text" name="auteur" id="auteurInput" class="input" placeholder="Ex: Mouhamadou Lamine Niang" value="Étudiant Testeur" required>
                </div>
                <div class="form_group">
                    <label class="form_label">Votre Commentaire (Testez les payloads XSS)</label>
                    <textarea name="content" id="commentInput" class="input" rows="3" placeholder="Saisissez un commentaire ou un payload XSS..." required></textarea>
                    
                    <div class="payload_btns">
                        <span style="font-size:11px; color:var(--text-3); align-self:center;">Payloads de démonstration :</span>
                        <button type="button" class="btn_payload" onclick="setPayload('&lt;script&gt;alert(\'XSS Détecté sur VoteNow !\')&lt;/script&gt;')">
                            <i class="fa-solid fa-code"></i> &lt;script&gt;alert('XSS')&lt;/script&gt;
                        </button>
                        <button type="button" class="btn_payload" onclick="setPayload('&lt;img src=x onerror=&quot;alert(\'XSS via balise IMG !\')&quot;&gt;')">
                            <i class="fa-solid fa-image"></i> &lt;img src=x onerror="alert('XSS!')"&gt;
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn_primary"><i class="fa-solid fa-paper-plane"></i> Publier le commentaire</button>
            </form>
        </div>

        <!-- Liste des commentaires -->
        <h3 style="margin-bottom:14px; font-size:16px;"><i class="fa-solid fa-comments"></i> Retours et Avis publiés (<?= count($commentaires) ?>)</h3>
        
        <?php foreach ($commentaires as $cm): ?>
            <div class="comment_card">
                <div style="display:flex; justify-content:space-between;">
                    <div class="comment_author"><i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($cm['auteur'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="comment_date"><?= htmlspecialchars($cm['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                
                <div class="comment_body">
                    <?php if ($isSecure): ?>
                        <!-- CODE CORRIGÉ : Échappement des entités HTML -->
                        <div><?php echo htmlspecialchars($cm['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php else: ?>
                        <!-- CODE VULNÉRABLE : Affichage brut sans neutralisation -->
                        <div><?php echo $cm['content']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Explication pédagogique -->
        <div class="card" style="margin-top:28px; border-left:4px solid var(--blue);">
            <h4><i class="fa-solid fa-graduation-cap"></i> Comparatif Technique (Présentation Soutenance)</h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:12px;">
                <div class="code_box" style="border:1px solid rgba(239,68,68,0.3);">
                    <strong style="color:#ef4444;"><i class="fa-solid fa-times-circle"></i> Code Vulnérable :</strong>
                    <pre style="margin-top:6px; color:#fca5a5;">&lt;div&gt;&lt;?php echo $cm['content']; ?&gt;&lt;/div&gt;</pre>
                    <p style="font-size:11px; color:var(--text-3); margin-top:6px;">Le navigateur interprète le texte comme du code JavaScript exécutable.</p>
                </div>
                <div class="code_box" style="border:1px solid rgba(34,197,94,0.3);">
                    <strong style="color:#22c55e;"><i class="fa-solid fa-check-circle"></i> Code Corrigé :</strong>
                    <pre style="margin-top:6px; color:#86efac;">&lt;div&gt;&lt;?php echo htmlspecialchars($cm['content'], ENT_QUOTES, 'UTF-8'); ?&gt;&lt;/div&gt;</pre>
                    <p style="font-size:11px; color:var(--text-3); margin-top:6px;">Convertit &lt; en &amp;lt;, &gt; en &amp;gt;, empêchant toute exécution.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setPayload(code) {
            document.getElementById('commentInput').value = code;
        }
    </script>
</body>
</html>
