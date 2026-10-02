<?php
/**
 * VoteNow - Front Controller
 * Direction 3 (pulse) étudiants + Direction 2 (command) admin
 * Signature: 2-space indent, snake_case php, camelCase js */

require_once 'config/database.php';
secureSessionStart();

// session state 
$isUser   = isset($_SESSION['user_id']);
$isAdmin  = isset($_SESSION['admin_id']);
$userData = $_SESSION['user_data']  ?? [];
$adminData = $_SESSION['admin_data'] ?? [];

// paramètres app 
$appName  = getSetting('app_name', 'VoteNow');
$univNom  = getSetting('univ_nom', 'Université');
$csrfToken = csrfToken();

// logout 
if ($_GET['logout'] ?? false) {
  logAction('logout', 'Déconnexion', $_SESSION['user_id'] ?? null, $_SESSION['admin_id'] ?? null);
  session_unset();
  session_destroy();
  header('Location: index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($appName) ?> - Plateforme de vote universitaire sécurisée">
  <title><?= htmlspecialchars($appName) ?></title>

  <!-- fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">

  <!-- styles -->
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- variables JS injectées par PHP -->
<script>
  "use strict";
  const CSRF_TOKEN   = '<?= htmlspecialchars($csrfToken) ?>';
  const APP_ENV      = '<?= APP_ENV ?>';
  const APP_NAME     = '<?= htmlspecialchars($appName) ?>';
  const UNIV_NOM     = '<?= htmlspecialchars($univNom) ?>';
  <?php if ($isUser): ?>
  window.SESSION_USER = <?= json_encode([
    'id'          => $userData['id'] ?? null,
    'nom'         => $userData['nom'] ?? '',
    'prenom'      => $userData['prenom'] ?? '',
    'carte_identite' => $userData['carte_identite'] ?? '',
    'ufr_id'      => $userData['ufr_id'] ?? null,
    'filiere_id'  => $userData['filiere_id'] ?? null,
    'niveau'      => $userData['niveau'] ?? '',
  ], JSON_UNESCAPED_UNICODE) ?>;
  window.SESSION_ADMIN = null;
  <?php elseif ($isAdmin): ?>
  window.SESSION_USER  = null;
  window.SESSION_ADMIN = <?= json_encode([
    'id'        => $adminData['id'] ?? null,
    'username'  => $adminData['username'] ?? '',
    'role'      => $adminData['role'] ?? '',
    'perimetre' => $adminData['perimetre'] ?? '',
    'ufr_id'    => $adminData['ufr_id'] ?? null,
  ], JSON_UNESCAPED_UNICODE) ?>;
  <?php else: ?>
  window.SESSION_USER  = null;
  window.SESSION_ADMIN = null;
  <?php endif; ?>
</script>

<!-- navigation top bar -->
<nav class="nav_top" role="navigation" aria-label="Navigation principale">
  <a class="nav_brand" href="index.php" aria-label="<?= htmlspecialchars($appName) ?>">
    <img src="assets/images/logo.webp"
         alt="<?= htmlspecialchars($appName) ?>"
         class="nav_brand_logo"
         onerror="this.style.display='none'">
    <span class="nav_brand_text"><?= htmlspecialchars($appName) ?></span>
  </a>

  <!-- liens desktop -->
  <div class="nav_links" id="navLinks">
    <button class="nav_link active" data-page="accueil" onclick="showPage('accueil')">
      <i class="fa-solid fa-house"></i> Accueil
    </button>
    <button class="nav_link" data-page="elections" onclick="showPage('elections');loadElections()">
      <i class="fa-solid fa-ballot-check"></i> Élections
    </button>
    <button class="nav_link" data-page="resultats" onclick="showPage('resultats');loadResultats()">
      <i class="fa-solid fa-chart-bar"></i> Résultats
    </button>
    <?php if ($isUser): ?>
    <button class="nav_link" data-page="candidature" onclick="showPage('candidature')">
      <i class="fa-solid fa-file-signature"></i> Candidature
    </button>
    <button class="nav_link" data-page="profil" onclick="showPage('profil');loadProfil()">
      <i class="fa-solid fa-user"></i> Mon espace
    </button>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <button class="nav_link" data-page="admin" onclick="showPage('admin');initAdminLayout()">
      <i class="fa-solid fa-shield-halved"></i> Administration
    </button>
    <?php endif; ?>
  </div>

  <!-- droite nav -->
  <div class="nav_right">
    <button class="nav_icon_btn" id="themeBtn" onclick="toggleTheme()" title="Changer de thème" aria-label="Thème">
      <i class="fa-solid fa-moon"></i>
    </button>

    <?php if ($isUser): ?>
    <button class="nav_icon_btn" data-page="notifications" onclick="showPage('notifications');loadNotifications()" title="Notifications" aria-label="Notifications">
      <i class="fa-solid fa-bell"></i>
      <span class="notif_dot" id="navNotifDot" style="display:none"></span>
    </button>
    <?php endif; ?>

    <?php if ($isUser || $isAdmin): ?>
    <div class="avatar" style="cursor:pointer" title="<?= htmlspecialchars($isUser ? ($userData['prenom'] ?? '') . ' ' . ($userData['nom'] ?? '') : ($adminData['username'] ?? '')) ?>"
         onclick="<?= $isAdmin ? "showPage('admin');initAdminLayout()" : "showPage('profil');loadProfil()" ?>">
      <?php
        $initials = $isUser
          ? strtoupper(substr($userData['prenom'] ?? 'U', 0, 1) . substr($userData['nom'] ?? '', 0, 1))
          : strtoupper(substr($adminData['username'] ?? 'A', 0, 2));
        echo htmlspecialchars($initials);
      ?>
    </div>
    <a href="?logout=1" class="nav_icon_btn" title="Déconnexion" aria-label="Déconnexion" style="color:rgba(255,100,100,0.75)">
      <i class="fa-solid fa-right-from-bracket"></i>
    </a>
    <?php else: ?>
    <button class="nav_icon_btn" style="color:rgba(255,255,255,0.8)" onclick="showPage('auth')" title="Connexion">
      <i class="fa-solid fa-right-to-bracket"></i>
    </button>
    <?php endif; ?>

    <!-- bouton menu mobile -->
    <button class="mobile_menu_btn" id="mobileMenuBtn" onclick="toggleMobileMenu()" aria-label="Menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</nav>

<!-- menu mobile déroulant -->
<div class="mobile_nav" id="mobileNav" role="navigation" aria-label="Menu mobile">
  <button class="nav_link active" data-page="accueil" onclick="showPage('accueil')">
    <i class="fa-solid fa-house"></i> Accueil
  </button>
  <button class="nav_link" data-page="elections" onclick="showPage('elections');loadElections()">
    <i class="fa-solid fa-ballot-check"></i> Élections
  </button>
  <button class="nav_link" data-page="resultats" onclick="showPage('resultats');loadResultats()">
    <i class="fa-solid fa-chart-bar"></i> Résultats
  </button>
  <?php if ($isUser): ?>
  <button class="nav_link" data-page="profil" onclick="showPage('profil');loadProfil()">
    <i class="fa-solid fa-user"></i> Mon espace
  </button>
  <?php endif; ?>
  <?php if ($isAdmin): ?>
  <button class="nav_link" data-page="admin" onclick="showPage('admin');initAdminLayout()">
    <i class="fa-solid fa-shield-halved"></i> Administration
  </button>
  <?php endif; ?>
  <?php if (!$isUser && !$isAdmin): ?>
  <button class="nav_link" onclick="showPage('auth')">
    <i class="fa-solid fa-right-to-bracket"></i> Connexion
  </button>
  <?php endif; ?>
  <?php if ($isUser || $isAdmin): ?>
  <a href="?logout=1" class="nav_link" style="color:rgba(220,38,38,0.85)">
    <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
  </a>
  <?php endif; ?>
</div>


<!-- Pages -->

<!-- PAGE ACCUEIL -->
<div id="page_accueil" class="page active">
  <!-- hero direction 3 -->
  <div class="hero">
    <div class="hero_inner">
      <div class="hero_label">
        <i class="fa-solid fa-graduation-cap"></i>
        <?= htmlspecialchars($univNom) ?>
      </div>
      <?php if ($isUser): ?>
      <h1>Bonjour, <span><?= htmlspecialchars($userData['prenom'] ?? 'Étudiant') ?></span> 👋</h1>
      <p class="hero_sub">Participez aux élections universitaires de manière sécurisée et anonyme.</p>
      <?php elseif ($isAdmin): ?>
      <h1>Espace <span>Administration</span></h1>
      <p class="hero_sub">Gérez les élections, candidatures et résultats depuis ce tableau de bord.</p>
      <?php else: ?>
      <h1>Votez pour vos <span>représentants</span></h1>
      <p class="hero_sub">Participez aux élections universitaires de manière sécurisée, transparente et anonyme.</p>
      <?php endif; ?>
      <div class="hero_stats">
        <div class="hero_stat">
          <div class="hero_stat_val" id="hero_votes">-</div>
          <div class="hero_stat_lbl">Votes exprimés</div>
        </div>
        <div class="hero_stat">
          <div class="hero_stat_val" id="hero_elections">-</div>
          <div class="hero_stat_lbl">Élections actives</div>
        </div>
        <div class="hero_stat">
          <div class="hero_stat_val" id="hero_etudiants">-</div>
          <div class="hero_stat_lbl">Étudiants inscrits</div>
        </div>
      </div>
    </div>
  </div>

  <div class="page_content">
    <?php if ($isUser): ?>
    <div style="margin-bottom:10px" id="user_ufr_tag"></div>
    <?php endif; ?>

    <!-- stats cards -->
    <div id="accueil_stats">
      <div class="stats_grid">
        <div class="skeleton" style="height:78px;border-radius:12px;"></div>
        <div class="skeleton" style="height:78px;border-radius:12px;"></div>
        <div class="skeleton" style="height:78px;border-radius:12px;"></div>
        <div class="skeleton" style="height:78px;border-radius:12px;"></div>
      </div>
    </div>

    <!-- élections -->
    <div class="flex items_center justify_between" style="margin-bottom:10px;margin-top:4px">
      <div class="section_label" style="margin-bottom:0">Élections disponibles</div>
      <button class="btn btn_ghost btn_sm" onclick="loadAccueil()">
        <i class="fa-solid fa-rotate-right"></i> Actualiser
      </button>
    </div>
    <div id="accueil_elections">
      <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface)">
        <div style="padding:12px 14px"><div class="skeleton" style="height:36px;border-radius:6px"></div></div>
        <div style="padding:12px 14px"><div class="skeleton" style="height:36px;border-radius:6px"></div></div>
      </div>
    </div>
  </div>
</div>


<!-- PAGE ÉLECTIONS -->
<div id="page_elections" class="page">
  <div id="page_elections_content">
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div class="hero_label"><i class="fa-solid fa-ballot-check"></i> Élections</div>
        <h1>Toutes les <span>élections</span></h1>
      </div>
    </div>
    <div class="page_content">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
        <div class="skeleton" style="height:220px;border-radius:16px"></div>
        <div class="skeleton" style="height:220px;border-radius:16px"></div>
        <div class="skeleton" style="height:220px;border-radius:16px"></div>
      </div>
    </div>
  </div>
</div>


<!-- PAGE VOTE -->
<div id="page_vote" class="page">
  <div class="page_content">
    <div class="skeleton" style="height:200px;border-radius:12px"></div>
  </div>
</div>


<!-- PAGE RÉSULTATS -->
<div id="page_resultats" class="page">
  <!-- contenu chargé dynamiquement par loadResultats() -->
</div>


<!-- PAGE CANDIDATURE -->
<div id="page_candidature" class="page">
  <div class="hero" style="padding:18px 20px">
    <div class="hero_inner">
      <div class="hero_label"><i class="fa-solid fa-file-signature"></i> Candidature</div>
      <h1>Déposer ma <span>candidature</span></h1>
      <p class="hero_sub" id="cand_form_election_titre">Chargement...</p>
    </div>
  </div>
  <div class="page_content">
    <?php if (!$isUser): ?>
    <div class="alert alert_warning">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>Vous devez être connecté pour déposer une candidature.</span>
    </div>
    <?php else: ?>
    <div id="cand_form_alert"></div>
    <div class="card" style="max-width:600px">
      <div class="card_header">
        <div class="card_title"><i class="fa-solid fa-user-plus"></i> Votre candidature</div>
      </div>
      <div class="card_body">
        <div class="form_group">
          <label class="form_label">Nom complet <span class="form_required">*</span></label>
          <input type="text" id="cand_nom" class="input"
                 value="<?= htmlspecialchars(($userData['prenom'] ?? '') . ' ' . ($userData['nom'] ?? '')) ?>"
                 placeholder="Ex: Amadou DIALLO">
          <div class="form_hint">Tel qu'il apparaîtra sur la page de vote.</div>
        </div>
        <div class="form_group">
          <label class="form_label">Slogan <span class="form_required">*</span></label>
          <input type="text" id="cand_slogan" class="input" maxlength="150"
                 placeholder="Votre phrase de campagne (max 150 car.)"
                 oninput="document.getElementById('slogan_count').textContent=this.value.length+'/150'">
          <div class="char_count" id="slogan_count">0/150</div>
        </div>
        <div class="form_group">
          <label class="form_label">Description / Programme</label>
          <textarea id="cand_description" class="textarea" rows="5" maxlength="1000"
                    placeholder="Présentez-vous, vos motivations et vos engagements... (max 1000 car.)"
                    oninput="document.getElementById('desc_count').textContent=this.value.length+'/1000'"></textarea>
          <div class="char_count" id="desc_count">0/1000</div>
        </div>
        <div class="form_group">
          <label class="form_label">Photo</label>
          <div class="file_zone" onclick="document.getElementById('cand_photo_file').click()">
            <i class="fa-solid fa-camera"></i>
            <p id="cand_photo_label">Cliquer pour ajouter une photo</p>
            <small>JPG, PNG ou WEBP · Max 5 Mo</small>
            <input type="file" id="cand_photo_file" accept="image/jpeg,image/png,image/webp"
                   onchange="previewCandPhoto(this)">
          </div>
          <div id="cand_photo_preview" style="display:none;margin-top:8px">
            <img id="cand_photo_img" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid var(--border)">
          </div>
        </div>
        <div class="form_group">
          <label class="form_label">Programme PDF</label>
          <div class="file_zone" onclick="document.getElementById('cand_pdf_file').click()">
            <i class="fa-solid fa-file-pdf"></i>
            <p id="cand_pdf_label">Cliquer pour ajouter votre programme PDF</p>
            <small>PDF uniquement · Max 10 Mo</small>
            <input type="file" id="cand_pdf_file" accept="application/pdf"
                   onchange="document.getElementById('cand_pdf_label').textContent=this.files[0]?.name||'Programme sélectionné'">
          </div>
        </div>

        <!-- honeypot -->
        <div class="hp_field" aria-hidden="true">
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button class="btn btn_primary btn_full btn_lg" onclick="soumettreCandidat()">
          <i class="fa-solid fa-paper-plane"></i> Soumettre ma candidature
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>


<!-- PAGE PROFIL / MON ESPACE -->
<div id="page_profil" class="page">
  <div class="hero" style="padding:18px 20px">
    <div class="hero_inner">
      <div class="hero_label"><i class="fa-solid fa-user"></i> Mon espace</div>
      <h1>Votre <span>profil</span></h1>
    </div>
  </div>
  <div class="page_content">
    <div id="profil_content">
      <div style="display:grid;gap:12px">
        <div class="skeleton" style="height:120px;border-radius:12px"></div>
        <div class="skeleton" style="height:180px;border-radius:12px"></div>
      </div>
    </div>
  </div>
</div>


<!-- PAGE NOTIFICATIONS -->
<div id="page_notifications" class="page">
  <div class="hero" style="padding:18px 20px">
    <div class="hero_inner">
      <div class="hero_label"><i class="fa-solid fa-bell"></i> Notifications</div>
      <h1>Vos <span>notifications</span></h1>
    </div>
  </div>
  <div class="page_content">
    <div id="notifications_content">
      <div style="display:grid;gap:8px">
        <div class="skeleton" style="height:60px;border-radius:8px"></div>
        <div class="skeleton" style="height:60px;border-radius:8px"></div>
        <div class="skeleton" style="height:60px;border-radius:8px"></div>
      </div>
    </div>
  </div>
</div>


<!-- PAGE AUTH (connexion / inscription) -->
<div id="page_auth" class="page" <?= ($isUser || $isAdmin) ? '' : 'style="display:block"' ?>>
  <div class="auth_page">
    <div class="auth_box">
      <div class="auth_logo">
        <img src="assets/images/logo.webp" alt="<?= htmlspecialchars($appName) ?>"
             style="height:44px;margin:0 auto" onerror="this.style.display='none'">
        <h2><?= htmlspecialchars($appName) ?></h2>
        <p>Plateforme de vote universitaire sécurisée</p>
      </div>

      <!-- tabs -->
      <div class="auth_tabs" id="authTabs">
        <button class="auth_tab active" onclick="switchAuthTab('connexion')">
          <i class="fa-solid fa-right-to-bracket"></i> Connexion
        </button>
        <button class="auth_tab" onclick="switchAuthTab('inscription')">
          <i class="fa-solid fa-user-plus"></i> Inscription
        </button>
        <button class="auth_tab" onclick="switchAuthTab('admin')">
          <i class="fa-solid fa-shield-halved"></i> Admin
        </button>
      </div>

      <!-- formulaire connexion étudiant -->
      <div id="form_connexion">
        <div id="login_alert"></div>
        <div class="form_group">
          <label class="form_label">N° Carte d'identité</label>
          <input type="text" id="login_carte" class="input" placeholder="Ex: SN-2024-001"
                 autocomplete="username">
        </div>
        <div class="form_group">
          <label class="form_label">Mot de passe</label>
          <input type="password" id="login_pwd" class="input" placeholder="••••••••"
                 autocomplete="current-password"
                 onkeydown="if(event.key==='Enter')soumettreLogin()">
        </div>
        <!-- honeypot -->
        <div class="hp_field" aria-hidden="true">
          <input type="text" name="_hp" tabindex="-1">
        </div>
        <button class="btn btn_primary btn_full" id="login_btn" onclick="soumettreLogin()">
          <i class="fa-solid fa-right-to-bracket"></i> Se connecter
        </button>
      </div>

      <!-- formulaire inscription -->
      <div id="form_inscription" style="display:none">
        <div id="register_alert"></div>
        <div class="form_group">
          <label class="form_label">N° Carte d'identité <span class="form_required">*</span></label>
          <input type="text" id="reg_carte" class="input" placeholder="Ex: SN-2024-001">
          <div class="form_hint">Doit figurer dans la liste officielle de l'université.</div>
        </div>
        <div class="form_group">
          <label class="form_label">UFR <span class="form_required">*</span></label>
          <select id="reg_ufr" class="select" onchange="loadRegFilieres()">
            <option value="">-- Sélectionner l'UFR --</option>
          </select>
        </div>
        <div class="form_row">
          <div class="form_group">
            <label class="form_label">Filière <span class="form_required">*</span></label>
            <select id="reg_filiere" class="select" disabled onchange="loadRegNiveaux()">
              <option value="">-- Filière --</option>
            </select>
          </div>
          <div class="form_group">
            <label class="form_label">Niveau <span class="form_required">*</span></label>
            <select id="reg_niveau" class="select" disabled>
              <option value="">-- Niveau --</option>
            </select>
          </div>
        </div>
        <div class="form_group">
          <label class="form_label">Mot de passe <span class="form_required">*</span></label>
          <input type="password" id="reg_pwd" class="input" placeholder="Minimum 6 caractères"
                 autocomplete="new-password">
        </div>
        <!-- honeypot -->
        <div class="hp_field" aria-hidden="true">
          <input type="text" name="phone_number" tabindex="-1">
        </div>
        <button class="btn btn_primary btn_full" id="register_btn" onclick="soumettreInscription()">
          <i class="fa-solid fa-user-plus"></i> S'inscrire
        </button>
      </div>

      <!-- formulaire admin -->
      <div id="form_admin" style="display:none">
        <div id="admin_login_alert"></div>
        <div class="form_group">
          <label class="form_label">Identifiant administrateur</label>
          <input type="text" id="admin_username" class="input" placeholder="admin_ufr_st"
                 autocomplete="username">
        </div>
        <div class="form_group">
          <label class="form_label">Mot de passe</label>
          <input type="password" id="admin_pwd" class="input" placeholder="••••••••"
                 autocomplete="current-password"
                 onkeydown="if(event.key==='Enter')soumettreLoginAdmin()">
        </div>
        <button class="btn btn_primary btn_full" id="admin_login_btn" onclick="soumettreLoginAdmin()">
          <i class="fa-solid fa-shield-halved"></i> Connexion Admin
        </button>
      </div>
    </div>
  </div>
</div>


<!-- PAGE ADMIN (direction 2 - command) -->
<div id="page_admin" class="page">
  <div class="admin_layout">
    <!-- sidebar -->
    <div class="sidebar" id="sidebar" role="navigation" aria-label="Navigation admin"></div>

    <!-- contenu admin -->
    <div class="admin_main">
      <!-- alert bar -->
      <div class="alert_bar" id="admin_alert_bar" style="display:none">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span></span>
      </div>

      <!-- topbar admin -->
      <div class="admin_topbar">
        <div class="admin_topbar_title" id="admin_topbar_title">Tableau de bord</div>
        <div class="admin_topbar_actions">
          <span style="font-size:11px;color:var(--text-3)">
            <?= htmlspecialchars($adminData['username'] ?? '') ?>
            <?php if (!empty($adminData['role'])): ?>
            · <span style="color:var(--blue);font-weight:600"><?= htmlspecialchars($adminData['role'] === 'superadmin' ? 'Super Admin' : 'Admin') ?></span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <!-- filter bar -->
      <div class="filter_bar" id="admin_filter_bar"></div>

      <!-- contenu principal -->
      <div class="admin_content" id="admin_content">
        <div style="display:grid;gap:12px">
          <div class="skeleton" style="height:78px;border-radius:12px"></div>
          <div class="skeleton" style="height:78px;border-radius:12px"></div>
          <div class="skeleton" style="height:78px;border-radius:12px"></div>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Modals globales -->

<!-- modal générique admin -->
<div id="adminModal" class="modal" role="dialog" aria-modal="true">
  <div class="modal_box lg">
    <div class="modal_header">
      <div class="modal_title"><i class="fa-solid fa-gear"></i> <span id="adminModalTitle">Action</span></div>
      <button class="modal_close" onclick="closeModal('adminModal')" aria-label="Fermer">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal_body" id="adminModalBody"></div>
    <div class="modal_footer" id="adminModalFooter">
      <button class="btn btn_outline" onclick="closeModal('adminModal')">Fermer</button>
    </div>
  </div>
</div>

<!-- modal confirmation vote -->
<div id="confirmModal" class="modal" role="dialog" aria-modal="true">
  <div class="modal_box">
    <div class="modal_header">
      <div class="modal_title">
        <i class="fa-solid fa-circle-question"></i>
        <span id="confirmModalTitle">Confirmer</span>
      </div>
      <button class="modal_close" onclick="closeModal('confirmModal')" aria-label="Fermer">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal_body">
      <div id="confirmModalMessage"></div>
      <div id="confirmModalInputWrap" class="confirm_input_wrap" style="display:none">
        <p style="font-size:12px;color:var(--text-2);margin-bottom:6px">
          Tapez <strong class="confirm_target_name" id="confirmModalName"></strong> pour confirmer :
        </p>
        <input type="text" id="confirmModalInput" class="input" autocomplete="off">
      </div>
    </div>
    <div class="modal_footer">
      <button class="btn btn_outline" onclick="closeModal('confirmModal')">Annuler</button>
      <button class="btn btn_primary" id="confirmModalBtn">Confirmer</button>
    </div>
  </div>
</div>

<!-- modal motif -->
<div id="motifModal" class="modal" role="dialog" aria-modal="true">
  <div class="modal_box">
    <div class="modal_header">
      <div class="modal_title">
        <i class="fa-solid fa-comment"></i>
        <span id="motifModalTitle">Motif</span>
      </div>
      <button class="modal_close" onclick="closeModal('motifModal')" aria-label="Fermer">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal_body">
      <div class="form_group">
        <label class="form_label">Motif <span class="form_required">*</span></label>
        <textarea id="motifModalInput" class="textarea" rows="3"></textarea>
      </div>
    </div>
    <div class="modal_footer">
      <button class="btn btn_outline" onclick="closeModal('motifModal')">Annuler</button>
      <button class="btn btn_primary" id="motifModalConfirm">Confirmer</button>
    </div>
  </div>
</div>


<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Aperçu photo">
  <button class="lightbox_close" onclick="closeLightbox()" aria-label="Fermer">
    <i class="fa-solid fa-xmark"></i>
  </button>
  <img id="lightboxImg" src="" alt="">
</div>


<!-- BOTTOM TAB BAR (mobile direction 3) -->
<nav class="bottom_nav" id="bottomNav" aria-label="Navigation mobile">
  <div class="bottom_nav_inner">
    <button class="bottom_tab active" data-page="accueil" onclick="showPage('accueil')" aria-label="Accueil">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">🏠</div>
      <div class="bottom_tab_label">Accueil</div>
    </button>
    <button class="bottom_tab" data-page="elections" onclick="showPage('elections');loadElections()" aria-label="Élections">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">📋</div>
      <div class="bottom_tab_label">Élections</div>
    </button>
    <button class="bottom_tab" data-page="resultats" onclick="showPage('resultats');loadResultats()" aria-label="Résultats">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">📊</div>
      <div class="bottom_tab_label">Résultats</div>
    </button>
    <?php if ($isUser): ?>
    <button class="bottom_tab" data-page="notifications" onclick="showPage('notifications');loadNotifications()" aria-label="Notifications">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">🔔</div>
      <div class="bottom_tab_label">Notifs</div>
      <div class="bottom_tab_notif" id="bottomNotifBadge" style="display:none">0</div>
    </button>
    <button class="bottom_tab" data-page="profil" onclick="showPage('profil');loadProfil()" aria-label="Mon espace">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">👤</div>
      <div class="bottom_tab_label">Moi</div>
    </button>
    <?php elseif ($isAdmin): ?>
    <button class="bottom_tab" data-page="admin" onclick="showPage('admin');initAdminLayout()" aria-label="Administration">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">🛡️</div>
      <div class="bottom_tab_label">Admin</div>
    </button>
    <?php else: ?>
    <button class="bottom_tab" data-page="auth" onclick="showPage('auth')" aria-label="Connexion">
      <div class="bottom_tab_indicator"></div>
      <div class="bottom_tab_icon">🔑</div>
      <div class="bottom_tab_label">Connexion</div>
    </button>
    <?php endif; ?>
  </div>
</nav>


<!-- Scripts -->
<script src="js/utils.js"></script>
<script src="js/elections.js"></script>
<script src="js/admin.js"></script>

<script>
"use strict";

// init application 
document.addEventListener("DOMContentLoaded", function() {
  // thème persistant
  initTheme();

  // page initiale depuis URL
  const urlParams = new URLSearchParams(window.location.search);
  const page_init = urlParams.get("p") || "accueil";

  <?php if (!$isUser && !$isAdmin): ?>
  // non connecté → page auth
  showPage("auth");
  loadAuthUfr();
  <?php else: ?>
  showPage(page_init === "auth" ? "accueil" : page_init);
  loadAccueil();
  <?php if ($isAdmin): ?>
  // si page admin dans URL, initialiser le layout
  if (page_init === "admin") {
    initAdminLayout();
  }
  <?php endif; ?>
  // notifications count
  checkNotifsCount();
  <?php endif; ?>
});

// auth tabs 
function switchAuthTab(tab) {
  document.querySelectorAll(".auth_tab").forEach((el, i) => {
    el.classList.toggle("active", ["connexion","inscription","admin"][i] === tab);
  });
  document.getElementById("form_connexion").style.display  = tab === "connexion"  ? "block" : "none";
  document.getElementById("form_inscription").style.display = tab === "inscription" ? "block" : "none";
  document.getElementById("form_admin").style.display       = tab === "admin"       ? "block" : "none";
}

// login étudiant 
async function soumettreLogin() {
  const carte = document.getElementById("login_carte")?.value.trim();
  const pwd   = document.getElementById("login_pwd")?.value;
  const btn   = document.getElementById("login_btn");
  if (!carte || !pwd) { showAlert("login_alert", "Carte et mot de passe obligatoires."); return; }

  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Connexion...`; }

  const data = await api("api/auth.php", {
    method: "POST",
    body: JSON.stringify({ action: "login", carte, password: pwd, _hp: "" })
  });

  if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-right-to-bracket"></i> Se connecter`; }

  if (data.success) {
    // régénérer le CSRF token reçu
    if (data.csrf) window.CSRF_TOKEN = data.csrf;
    toast("Connexion réussie !", "success");
    setTimeout(() => location.reload(), 500);
  } else {
    showAlert("login_alert", data.message || "Identifiants incorrects.");
  }
}

// login admin 
async function soumettreLoginAdmin() {
  const username = document.getElementById("admin_username")?.value.trim();
  const pwd      = document.getElementById("admin_pwd")?.value;
  const btn      = document.getElementById("admin_login_btn");
  if (!username || !pwd) { showAlert("admin_login_alert", "Identifiant et mot de passe obligatoires."); return; }

  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Connexion...`; }

  const data = await api("api/auth.php", {
    method: "POST",
    body: JSON.stringify({ action: "login_admin", username, password: pwd })
  });

  if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-shield-halved"></i> Connexion Admin`; }

  if (data.success) {
    if (data.csrf) window.CSRF_TOKEN = data.csrf;
    toast("Connexion admin réussie !", "success");
    setTimeout(() => location.reload(), 500);
  } else {
    showAlert("admin_login_alert", data.message || "Identifiants incorrects.");
  }
}

// inscription 
async function loadAuthUfr() {
  const data = await api("api/auth.php?action=ufr");
  const sel  = document.getElementById("reg_ufr");
  if (!sel) return;
  (data.ufr || []).forEach(u => {
    sel.innerHTML += `<option value="${u.id}">${esc(u.nom)}</option>`;
  });
}

async function loadRegFilieres() {
  const ufr_id = document.getElementById("reg_ufr")?.value;
  const sel    = document.getElementById("reg_filiere");
  const niv    = document.getElementById("reg_niveau");
  if (!ufr_id || !sel) return;
  sel.innerHTML = `<option value="">-- Filière --</option>`;
  sel.disabled  = true;
  if (niv) { niv.innerHTML = `<option value="">-- Niveau --</option>`; niv.disabled = true; }
  const data    = await api(`api/auth.php?action=filieres&ufr_id=${ufr_id}`);
  (data.filieres || []).forEach(f => { sel.innerHTML += `<option value="${f.id}">${esc(f.nom)}</option>`; });
  sel.disabled  = false;
}

async function loadRegNiveaux() {
  const fil_id = document.getElementById("reg_filiere")?.value;
  const sel    = document.getElementById("reg_niveau");
  if (!fil_id || !sel) return;
  sel.innerHTML = `<option value="">-- Niveau --</option>`;
  const data    = await api(`api/auth.php?action=niveaux&filiere_id=${fil_id}`);
  (data.niveaux || []).forEach(n => { sel.innerHTML += `<option value="${n}">${n}</option>`; });
  sel.disabled  = false;
}

async function soumettreInscription() {
  const carte     = document.getElementById("reg_carte")?.value.trim().toUpperCase();
  const pwd       = document.getElementById("reg_pwd")?.value;
  const ufr_id    = document.getElementById("reg_ufr")?.value;
  const filiere_id = document.getElementById("reg_filiere")?.value;
  const niveau    = document.getElementById("reg_niveau")?.value;
  const btn       = document.getElementById("register_btn");

  if (!carte || !pwd) { showAlert("register_alert", "Carte et mot de passe obligatoires."); return; }

  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Inscription...`; }

  const data = await api("api/auth.php", {
    method: "POST",
    body: JSON.stringify({ action: "register", carte, password: pwd, ufr_id, filiere_id, niveau, phone_number: "" })
  });

  if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-user-plus"></i> S'inscrire`; }

  if (data.success) {
    toast("Compte créé ! Vous pouvez vous connecter.", "success", 5000);
    switchAuthTab("connexion");
    const login_carte = document.getElementById("login_carte");
    if (login_carte) login_carte.value = carte;
  } else {
    showAlert("register_alert", data.message || "Erreur lors de l'inscription.");
  }
}

// candidature étudiant 
function previewCandPhoto(input) {
  const file    = input.files[0];
  const preview = document.getElementById("cand_photo_preview");
  const img     = document.getElementById("cand_photo_img");
  const label   = document.getElementById("cand_photo_label");
  if (!file) return;
  label.textContent  = file.name;
  if (preview && img) {
    preview.style.display = "block";
    const reader = new FileReader();
    reader.onload = (e) => { img.src = e.target.result; };
    reader.readAsDataURL(file);
  }
}

async function soumettreCandidat() {
  const page       = document.getElementById("page_candidature");
  const election_id = page?.dataset.election_id;
  const nom        = document.getElementById("cand_nom")?.value.trim();
  const slogan     = document.getElementById("cand_slogan")?.value.trim();
  const desc       = document.getElementById("cand_description")?.value.trim();
  const photo_file = document.getElementById("cand_photo_file")?.files[0];
  const pdf_file   = document.getElementById("cand_pdf_file")?.files[0];

  if (!election_id) { showAlert("cand_form_alert", "Élection non sélectionnée. Accédez via la liste des élections.", "warning"); return; }
  if (!nom || !slogan) { showAlert("cand_form_alert", "Nom complet et slogan sont obligatoires."); return; }

  // encode photo en base64 si présente
  let photo_b64 = null;
  if (photo_file) {
    photo_b64 = await fileToBase64(photo_file);
  }

  let pdf_b64 = null;
  if (pdf_file) {
    pdf_b64 = await fileToBase64(pdf_file);
  }

  const data = await api("api/candidatures.php", {
    method: "POST",
    body: JSON.stringify({
      election_id,
      nom_complet:  nom,
      slogan,
      description: desc,
      photo:        photo_b64,
      programme:    pdf_b64,
      website:      ""
    })
  });

  if (data.success) {
    toast("Candidature soumise ! En attente de validation.", "success", 6000);
    showPage("accueil");
    loadAccueil();
  } else {
    showAlert("cand_form_alert", data.message || "Erreur lors de la soumission.");
  }
}

function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload  = () => resolve(reader.result);
    reader.onerror = () => reject(new Error("Lecture fichier échouée."));
    reader.readAsDataURL(file);
  });
}

// profil 
async function loadProfil() {
  const content = document.getElementById("profil_content");
  if (!content) return;
  content.innerHTML = skeletonCards(3, "100px");

  const [profil_data, cands_data, demandes_data] = await Promise.all([
    api("api/profil.php?action=profil"),
    api("api/candidatures.php?mes_candidatures=1"),
    api("api/profil.php?action=demandes")
  ]);

  const u      = profil_data.user || {};
  const cands  = cands_data.candidatures || [];
  const dems   = demandes_data.demandes || [];

  content.innerHTML = `
    <!-- infos profil -->
    <div class="card" style="margin-bottom:16px">
      <div class="card_body">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap">
          <div class="avatar xl">${esc((u.prenom||"U")[0].toUpperCase() + (u.nom||"")[0].toUpperCase())}</div>
          <div>
            <div style="font-size:1.1rem;font-weight:700;color:var(--text)">${esc(u.prenom||"")} ${esc(u.nom||"")}</div>
            <div style="font-size:12px;color:var(--text-2);margin-top:2px">${esc(u.carte_identite||"")}</div>
            <div style="margin-top:6px"><span class="ufr_tag"><i class="fa-solid fa-building-columns"></i> ${esc(u.ufr_nom||"-")} · ${esc(u.filiere_nom||"-")} · ${esc(u.niveau||"-")}</span></div>
          </div>
        </div>
        <div class="divider"></div>
        <!-- changement MDP -->
        <div class="section_label" style="margin-bottom:10px">Changer mon mot de passe</div>
        <div id="pwd_change_alert"></div>
        <div class="form_row">
          <div class="form_group">
            <label class="form_label">Mot de passe actuel</label>
            <input type="password" id="pwd_current" class="input" placeholder="••••••••">
          </div>
          <div class="form_group">
            <label class="form_label">Nouveau mot de passe</label>
            <input type="password" id="pwd_new" class="input" placeholder="Min 6 caractères">
          </div>
        </div>
        <button class="btn btn_outline btn_sm" onclick="changerMotDePasse()">
          <i class="fa-solid fa-key"></i> Modifier le mot de passe
        </button>
      </div>
    </div>

    <!-- candidatures en cours -->
    <div class="card" style="margin-bottom:16px">
      <div class="card_header">
        <div class="card_title"><i class="fa-solid fa-file-signature"></i> Mes candidatures</div>
      </div>
      ${cands.length === 0
        ? `<div class="empty_state" style="padding:24px"><i class="fa-solid fa-file-signature"></i><h3>Aucune candidature</h3></div>`
        : cands.map(c => `
          <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <div>
              <div style="font-size:12px;font-weight:500">${esc(c.election_titre||"-")}</div>
              <div style="font-size:11px;color:var(--text-3);">"${esc((c.slogan||"").slice(0,50))}"</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span class="tag ${c.statut==="validee"?"tag_ok":c.statut==="refusee"?"tag_ko":c.statut==="en_attente"?"tag_pend":"tag_arch"}">${esc(c.statut.replace("_"," "))}</span>
              ${c.statut==="en_attente" ? `<button class="btn btn_danger btn_sm" onclick="retirerCandidat(${c.id})"><i class="fa-solid fa-times"></i> Retirer</button>` : ""}
            </div>
          </div>
        `).join("")
      }
    </div>

    <!-- demande changement profil -->
    <div class="card">
      <div class="card_header">
        <div class="card_title"><i class="fa-solid fa-arrow-right-arrow-left"></i> Demande de changement de profil</div>
      </div>
      <div class="card_body">
        <div class="alert alert_info" style="margin-bottom:12px">
          <i class="fa-solid fa-circle-info"></i>
          <span>Si vous avez changé de filière ou de niveau, soumettez une demande. L'administration validera le changement.</span>
        </div>
        <div id="demande_alert"></div>
        ${dems.filter(d => d.statut==="en_attente").length > 0
          ? `<div class="alert alert_warning"><i class="fa-solid fa-clock"></i><span>Vous avez une demande en attente de validation.</span></div>`
          : `
            <div id="demande_form">
              <div class="form_group">
                <label class="form_label">Nouvelle filière</label>
                <select id="demande_filiere" class="select"><option value="">-- Filière --</option></select>
              </div>
              <div class="form_group">
                <label class="form_label">Nouveau niveau</label>
                <select id="demande_niveau" class="select"><option value="">-- Niveau --</option></select>
              </div>
              <button class="btn btn_outline" onclick="soumettreDemandeProf()">
                <i class="fa-solid fa-paper-plane"></i> Envoyer la demande
              </button>
            </div>
          `
        }
      </div>
    </div>
  `;
}

async function changerMotDePasse() {
  const current = document.getElementById("pwd_current")?.value;
  const nouveau = document.getElementById("pwd_new")?.value;
  if (!current || !nouveau) { showAlert("pwd_change_alert", "Remplissez les deux champs."); return; }
  if (nouveau.length < 6)   { showAlert("pwd_change_alert", "Nouveau mot de passe minimum 6 caractères."); return; }
  const data = await api("api/profil.php", {
    method: "POST",
    body: JSON.stringify({ action: "change_password", current_password: current, new_password: nouveau })
  });
  if (data.success) {
    toast("Mot de passe modifié ✓", "success");
    clearAlert("pwd_change_alert");
    document.getElementById("pwd_current").value = "";
    document.getElementById("pwd_new").value     = "";
  } else {
    showAlert("pwd_change_alert", data.message || "Erreur.");
  }
}

async function retirerCandidat(cand_id) {
  openConfirmModal({
    title:         "Retirer votre candidature",
    message:       `<p style="font-size:13px">Cette action est irréversible. Votre candidature sera retirée définitivement.</p>`,
    confirm_label: "Retirer",
    danger:        true,
    callback:      async () => {
      const data = await api("api/candidatures.php", {
        method: "PUT",
        body: JSON.stringify({ id: cand_id, action: "retirer" })
      });
      if (data.success) { toast("Candidature retirée.", "info"); loadProfil(); }
      else toast(data.message || "Erreur.", "error");
    }
  });
}

async function soumettreDemandeProf() {
  const filiere_id = document.getElementById("demande_filiere")?.value;
  const niveau     = document.getElementById("demande_niveau")?.value;
  if (!filiere_id || !niveau) { showAlert("demande_alert", "Filière et niveau obligatoires."); return; }
  const data = await api("api/profil.php", {
    method: "POST",
    body: JSON.stringify({ action: "demande_profil", filiere_id, niveau })
  });
  if (data.success) { toast("Demande envoyée ✓", "success"); loadProfil(); }
  else showAlert("demande_alert", data.message || "Erreur.");
}

// notifications 
async function loadNotifications() {
  const content = document.getElementById("notifications_content");
  if (!content) return;
  content.innerHTML = skeletonList(4);

  const data  = await api("api/profil.php?action=notifications");
  const notifs = data.notifications || [];

  if (!notifs.length) {
    content.innerHTML = emptyState("fa-bell", "Aucune notification", "Vous serez notifié des événements qui vous concernent.");
    return;
  }

  content.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span class="section_label" style="margin-bottom:0">${notifs.filter(n=>!n.lue).length} non lue(s)</span>
      <button class="btn btn_ghost btn_sm" onclick="marquerToutLu()">Tout marquer lu</button>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
      ${notifs.map(n => `
        <div style="padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:10px;
                    background:${!n.lue ? "var(--blue-pale)" : ""};cursor:pointer;transition:background 0.12s"
             onclick="lireNotif(${n.id},this,${n.lien ? `'${esc(n.lien)}'` : "null"})"
             onmouseover="this.style.background='var(--blue-pale)'"
             onmouseout="this.style.background='${!n.lue ? "var(--blue-pale)" : ""}'">
          <div style="width:8px;height:8px;border-radius:50%;background:${!n.lue ? "var(--blue)" : "var(--border-2)"};margin-top:5px;flex-shrink:0"></div>
          <div style="flex:1">
            <div style="font-size:12px;font-weight:${!n.lue ? "600" : "400"};color:var(--text)">${esc(n.titre)}</div>
            <div style="font-size:11px;color:var(--text-2);margin-top:2px">${esc(n.message)}</div>
            <div style="font-size:10px;color:var(--text-3);margin-top:3px">${timeAgo(n.created_at)}</div>
          </div>
        </div>
      `).join("")}
    </div>
  `;
}

async function lireNotif(id, el, lien) {
  await api("api/profil.php", {
    method: "POST",
    body: JSON.stringify({ action: "lire_notif", id })
  });
  if (el) { el.style.background = ""; el.querySelector("div:first-child").style.background = "var(--border-2)"; }
  checkNotifsCount();
  if (lien) {
    const parts = lien.split("=");
    if (parts[0] === "election" && parts[1]) ouvrirElection(parseInt(parts[1]));
  }
}

async function marquerToutLu() {
  await api("api/profil.php", { method: "POST", body: JSON.stringify({ action: "lire_tout" }) });
  loadNotifications();
  checkNotifsCount();
}

async function checkNotifsCount() {
  const data  = await api("api/profil.php?action=notifs_count");
  const count = data.count || 0;
  const dot   = document.getElementById("navNotifDot");
  const badge = document.getElementById("bottomNotifBadge");
  if (dot)   dot.style.display   = count > 0 ? "block" : "none";
  if (badge) {
    badge.style.display = count > 0 ? "flex" : "none";
    badge.textContent   = count;
  }
}

// override showPage pour sync bottom nav 
const _showPage_orig = window.showPage;
window.showPage = function(page_id) {
  _showPage_orig(page_id);
  // sync bottom nav
  document.querySelectorAll(".bottom_tab").forEach(tab => {
    tab.classList.toggle("active", tab.dataset.page === page_id);
  });
  // scroll vers le haut
  window.scrollTo({ top: 0, behavior: "smooth" });
};

// rafraîchir notifs toutes les 60 secondes
<?php if ($isUser): ?>
setInterval(checkNotifsCount, 60000);
<?php endif; ?>
</script>
</body>
</html>
