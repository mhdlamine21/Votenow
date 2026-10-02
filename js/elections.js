/**
 * VoteNow - Module Élections & Vote
 * Direction 3 (pulse) : Cartes avec compte à rebours + barre participation
 * Signature: 2-space indent, camelCase fonctions, snake_case vars locales */

"use strict";

// état local 
let elections_cache      = [];
let election_courante    = null;
let candidature_choisie  = null;
let vote_step            = 1;

// page accueil 
async function loadAccueil() {
  const container_stats = document.getElementById("accueil_stats");
  const container_list  = document.getElementById("accueil_elections");

  if (container_stats) {
    container_stats.innerHTML = `
      <div class="stats_grid">
        ${skeletonCards(4, "78px")}
      </div>
    `;
  }

  if (container_list) {
    container_list.innerHTML = `<div style="border:1px solid var(--border);border-radius:12px;overflow:hidden">${skeletonList(3)}</div>`;
  }

  const [stats_data, elections_data] = await Promise.all([
    api("api/admin.php?section=dashboard"),
    api("api/elections.php")
  ]);

  // stats cards
  const s = stats_data.stats || {};
  if (container_stats) {
    container_stats.innerHTML = `
      <div class="stats_grid">
        <div class="stat_card">
          <div class="stat_icon"><i class="fa-solid fa-ballot-check"></i></div>
          <div class="stat_content">
            <div class="stat_value">${s.elections_actives || 0}</div>
            <div class="stat_label">Élections en cours</div>
          </div>
        </div>
        <div class="stat_card">
          <div class="stat_icon" style="background:rgba(22,163,74,0.1);color:var(--green)"><i class="fa-solid fa-vote-yea"></i></div>
          <div class="stat_content">
            <div class="stat_value">${s.total_votes || 0}</div>
            <div class="stat_label">Votes exprimés</div>
          </div>
        </div>
        <div class="stat_card">
          <div class="stat_icon" style="background:rgba(168,85,247,0.1);color:#a855f7"><i class="fa-solid fa-graduation-cap"></i></div>
          <div class="stat_content">
            <div class="stat_value">${s.total_etudiants || 0}</div>
            <div class="stat_label">Étudiants inscrits</div>
          </div>
        </div>
        <div class="stat_card">
          <div class="stat_icon" style="background:rgba(202,138,4,0.1);color:var(--yellow)"><i class="fa-solid fa-file-signature"></i></div>
          <div class="stat_content">
            <div class="stat_value">${s.candidatures_attente || 0}</div>
            <div class="stat_label">Candidatures en attente</div>
          </div>
        </div>
      </div>
    `;
  }

  // hero stats
  const hero_votes     = document.getElementById("hero_votes");
  const hero_elections = document.getElementById("hero_elections");
  const hero_etudiants = document.getElementById("hero_etudiants");
  if (hero_votes)     hero_votes.textContent     = s.total_votes || 0;
  if (hero_elections) hero_elections.textContent = s.elections_actives || 0;
  if (hero_etudiants) hero_etudiants.textContent = s.total_etudiants || 0;

  // liste élections
  const elections = elections_data.elections || [];
  elections_cache = elections;
  if (container_list) {
    renderElectionsList(elections, container_list, true);
  }

  // UFR tag utilisateur
  const ufr_tag = document.getElementById("user_ufr_tag");
  if (ufr_tag && window.SESSION_USER) {
    ufr_tag.innerHTML = `
      <span class="ufr_tag">
        <i class="fa-solid fa-building-columns"></i>
        ${esc(window.SESSION_USER.ufr_nom || "UFR")} · ${esc(window.SESSION_USER.filiere_nom || "Filière")} · ${esc(window.SESSION_USER.niveau || "Niveau")}
      </span>
    `;
  }
}

// render liste élections (direction 3 - cartes pulse) 
function renderElectionsList(elections, container, compact = false) {
  if (!elections.length) {
    container.innerHTML = emptyState(
      "fa-ballot-check",
      "Aucune élection disponible",
      "Les prochaines élections apparaîtront ici.",
      window.SESSION_ADMIN ? `<button class="btn btn_primary btn_sm" onclick="showPage('admin');loadAdminTab('elections')"><i class="fa-solid fa-plus"></i> Créer une élection</button>` : ""
    );
    return;
  }

  if (compact) {
    // mode compact : liste simple (accueil)
    container.innerHTML = `
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        ${elections.slice(0, 6).map(el => renderElectionRow(el)).join("")}
        ${elections.length > 6 ? `
          <div style="padding:10px 14px;text-align:center;border-top:1px solid var(--border)">
            <button class="btn btn_ghost btn_sm" onclick="showPage('elections')">
              Voir toutes les élections (${elections.length}) <i class="fa-solid fa-arrow-right"></i>
            </button>
          </div>
        ` : ""}
      </div>
    `;
  } else {
    // mode grille : cartes pulse (page élections)
    container.innerHTML = `
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
        ${elections.map(el => renderElectionCard(el)).join("")}
      </div>
    `;
  }
}

// render row élection (liste compacte accueil) 
function renderElectionRow(el) {
  const phase_bar_cls = {
    vote: "vote", candidatures: "cand", candidatures_fermees: "cand",
    archivee: "arch", brouillon: "brou", vote_ferme: "arch"
  }[el.phase] || "arch";

  const eligibility = el.eligible === false
    ? `<span class="tag tag_inelig"><i class="fa-solid fa-ban" style="font-size:9px"></i> Non éligible</span>`
    : "";

  const nb_votants  = parseInt(el.nb_votes || 0);
  const nb_eligible = parseInt(el.nb_eligibles || 0);
  const pct         = nb_eligible > 0 ? Math.round(nb_votants / nb_eligible * 100) : 0;

  return `
    <div style="display:flex;align-items:center;gap:12px;padding:11px 14px;cursor:pointer;transition:background 0.12s;border-bottom:1px solid var(--border);"
         onclick="ouvrirElection(${el.id})"
         onmouseover="this.style.background='var(--blue-pale)'"
         onmouseout="this.style.background=''">
      <div style="width:34px;height:34px;border-radius:8px;background:var(--blue-10);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0">
        <i class="fa-solid ${el.phase === "vote" ? "fa-vote-yea" : el.phase === "archivee" ? "fa-archive" : "fa-file-signature"}"></i>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(el.titre)}</div>
        <div style="font-size:10px;color:var(--text-3);margin-top:1px">${scopeLabel(el)}</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
        ${phaseTag(el.phase, el.tour)}
        ${eligibility}
        ${el.phase === "vote" && nb_eligible > 0 ? `
          <div style="display:flex;align-items:center;gap:5px">
            <div style="width:50px;height:3px;background:var(--border);border-radius:2px;overflow:hidden">
              <div style="width:${pct}%;height:100%;background:var(--blue);border-radius:2px"></div>
            </div>
            <span style="font-size:9px;color:var(--text-3)">${pct}%</span>
          </div>
        ` : ""}
      </div>
    </div>
  `;
}

// render card élection (direction 3 - pulse) 
function renderElectionCard(el) {
  const phase_bar_cls = {
    vote: "vote", candidatures: "cand", candidatures_fermees: "cand",
    archivee: "arch", brouillon: "brou", vote_ferme: "arch"
  }[el.phase] || "arch";

  const nb_votants  = parseInt(el.nb_votes || 0);
  const nb_eligible = parseInt(el.nb_eligibles || 0);
  const pct         = nb_eligible > 0 ? Math.round(nb_votants / nb_eligible * 100) : 0;

  const eligibility_badge = el.eligible === false
    ? `<span class="tag tag_inelig" style="margin-left:4px"><i class="fa-solid fa-ban" style="font-size:9px"></i> Non éligible</span>`
    : "";

  const countdown_html = (el.phase === "vote" && el.date_vote_fin)
    ? renderCountdown(el.date_vote_fin)
    : "";

  const cta = (() => {
    if (el.phase === "vote" && el.eligible !== false) {
      if (el.deja_vote) {
        return `
          <div style="display:flex;gap:6px">
            <button class="btn btn_outline btn_full btn_sm" onclick="voirResultats(${el.id})">
              <i class="fa-solid fa-chart-bar"></i> Voir les résultats
            </button>
          </div>
        `;
      }
      return `
        <button class="btn btn_primary btn_full" onclick="ouvrirElection(${el.id})">
          <i class="fa-solid fa-vote-yea"></i> Voter maintenant →
        </button>
      `;
    }
    if (el.phase === "candidatures" && el.eligible !== false && !el.est_candidat) {
      return `
        <button class="btn btn_outline btn_full" onclick="ouvrirCandidature(${el.id})">
          <i class="fa-solid fa-file-signature"></i> Déposer ma candidature
        </button>
      `;
    }
    if (el.phase === "vote_ferme" || el.phase === "archivee") {
      return `
        <button class="btn btn_ghost btn_full btn_sm" onclick="voirResultats(${el.id})">
          <i class="fa-solid fa-chart-bar"></i> Voir les résultats
        </button>
      `;
    }
    return `
      <button class="btn btn_ghost btn_full btn_sm" onclick="ouvrirElection(${el.id})">
        <i class="fa-solid fa-eye"></i> Consulter
      </button>
    `;
  })();

  return `
    <div class="el_card">
      <div class="el_card_phase_bar ${phase_bar_cls}"></div>
      <div class="el_card_body">
        <div class="el_card_header">
          <div>
            ${phaseTag(el.phase, el.tour)}
            ${el.tour > 1 ? `<span class="tag tag_tour2" style="margin-left:4px">⚡ Tour ${el.tour}</span>` : ""}
            ${eligibility_badge}
          </div>
        </div>
        <div class="el_card_title">${esc(el.titre)}</div>
        <div class="el_card_meta">
          <span>${scopeLabel(el)}</span>
          <div class="el_card_dot"></div>
          <span>${el.nb_candidatures || 0} candidat(s)</span>
        </div>

        ${el.phase === "vote" ? `
          <div class="part_section">
            <div class="part_row">
              <span class="part_label">Participation</span>
              <span class="part_pct">${pct}%</span>
            </div>
            <div class="part_track">
              <div class="part_fill blue" style="width:${pct}%"></div>
            </div>
            ${countdown_html}
          </div>
        ` : ""}

        ${el.deja_vote ? `
          <div class="alert alert_success" style="margin-bottom:10px;padding:7px 10px">
            <i class="fa-solid fa-circle-check"></i>
            <span>Vous avez déjà voté</span>
          </div>
        ` : ""}
      </div>
      <div class="el_card_cta">${cta}</div>
    </div>
  `;
}

// ouvrir une élection 
async function ouvrirElection(election_id) {
  const page_vote = document.getElementById("page_vote");
  if (!page_vote) return;

  showPage("vote");

  page_vote.innerHTML = `
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div class="skeleton" style="height:20px;width:60%;border-radius:6px;margin-bottom:8px;background:rgba(255,255,255,0.1)"></div>
        <div class="skeleton" style="height:14px;width:35%;border-radius:4px;background:rgba(255,255,255,0.07)"></div>
      </div>
    </div>
    <div class="page_content">
      <div class="cands_grid">${skeletonCards(4, "180px")}</div>
    </div>
  `;

  const data = await api(`api/elections.php?id=${election_id}`);
  if (!data.success) {
    page_vote.innerHTML = `
      <div class="page_content">
        ${emptyState("fa-circle-xmark", "Élection introuvable", data.message || "")}
      </div>
    `;
    return;
  }

  const el = data.election;
  election_courante   = el;
  candidature_choisie = null;
  vote_step           = 1;

  renderPageVote(el, data.candidatures || [], data.deja_vote, data.eligible);
}

// render page vote 
function renderPageVote(el, candidatures, deja_vote, eligible) {
  const page_vote = document.getElementById("page_vote");
  if (!page_vote) return;

  // phase banner
  const phase_banner = (() => {
    if (deja_vote) {
      return `
        <div class="phase_banner vote">
          <i class="fa-solid fa-circle-check"></i>
          <div><strong>Vote enregistré ✓</strong> Vous avez déjà participé à cette élection.</div>
        </div>
      `;
    }
    if (el.phase === "vote" && el.tour > 1) {
      return `
        <div class="phase_banner tour2">
          <i class="fa-solid fa-bolt"></i>
          <div><strong>2ème Tour</strong> Suite à une égalité, ${el.nb_candidatures} candidats s'affrontent.</div>
        </div>
      `;
    }
    if (el.phase === "vote") {
      return `
        <div class="phase_banner vote">
          <i class="fa-solid fa-vote-yea"></i>
          <div><strong>Vote ouvert</strong> Sélectionnez un candidat et confirmez votre choix.</div>
        </div>
      `;
    }
    if (el.phase === "candidatures_fermees") {
      return `
        <div class="phase_banner closed">
          <i class="fa-solid fa-clock"></i>
          <div><strong>Candidatures fermées</strong> Le vote n'a pas encore commencé.</div>
        </div>
      `;
    }
    return "";
  })();

  const nb_votants  = parseInt(el.nb_votes || 0);
  const nb_eligible = parseInt(el.nb_eligibles || 0);
  const pct         = nb_eligible > 0 ? Math.round(nb_votants / nb_eligible * 100) : 0;

  page_vote.innerHTML = `
    <!-- hero election -->
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap">
          ${phaseTag(el.phase, el.tour)}
          ${el.tour > 1 ? `<span class="tag tag_tour2">⚡ Tour ${el.tour}</span>` : ""}
        </div>
        <h1 style="font-size:clamp(1.1rem,2.5vw,1.5rem)">${esc(el.titre)}</h1>
        <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span>${scopeLabel(el)}</span>
          ${el.date_vote_fin ? `<span>· Ferme ${formatDateTime(el.date_vote_fin)}</span>` : ""}
        </div>
        ${el.phase === "vote" && nb_eligible > 0 ? `
          <div style="margin-top:14px;max-width:300px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
              <span style="font-size:10px;color:rgba(255,255,255,0.45)">Participation</span>
              <span style="font-size:11px;font-weight:700;color:#fff">${pct}%</span>
            </div>
            <div class="part_track" style="height:6px;background:rgba(255,255,255,0.1)">
              <div class="part_fill blue" style="width:${pct}%;background:rgba(59,130,246,0.8)"></div>
            </div>
            <div style="font-size:9px;color:rgba(255,255,255,0.3);margin-top:4px">${nb_votants} sur ${nb_eligible} électeurs</div>
          </div>
        ` : ""}
      </div>
    </div>

    <div class="page_content">
      ${phase_banner}

      ${el.phase === "vote" && !deja_vote && eligible !== false ? `
        <!-- steps vote -->
        <div class="vote_steps" id="vote_steps">
          <div class="vstep active" id="step_1">
            <div class="vstep_circle">1</div>
            <span>Choisir</span>
          </div>
          <div class="vstep_line"></div>
          <div class="vstep" id="step_2">
            <div class="vstep_circle">2</div>
            <span>Confirmer</span>
          </div>
          <div class="vstep_line"></div>
          <div class="vstep" id="step_3">
            <div class="vstep_circle">3</div>
            <span>Terminé</span>
          </div>
        </div>
      ` : ""}

      <!-- candidats -->
      <div class="section_label" style="margin-bottom:12px">
        ${candidatures.length} candidat(s) - cliquez sur la photo pour agrandir
      </div>

      <div class="cands_grid" id="cands_grid">
        ${candidatures.length === 0
          ? emptyState("fa-user-group", "Aucun candidat validé", "Les candidats apparaîtront ici après validation.")
          : candidatures.map(c => renderCandCard(c, el.phase === "vote" && !deja_vote && eligible !== false)).join("")
        }
      </div>

      ${el.phase === "vote" && !deja_vote && eligible !== false ? `
        <div id="vote_cta_bar" style="position:sticky;bottom:0;background:var(--surface);border-top:1px solid var(--border);padding:12px 0;margin-top:20px;z-index:50">
          <button class="btn btn_primary btn_full btn_lg" id="btn_voter" disabled
                  onclick="confirmerVote()"
                  style="max-width:500px;margin:0 auto;display:flex">
            <i class="fa-solid fa-vote-yea"></i>
            <span id="btn_voter_text">Sélectionnez un candidat pour voter</span>
          </button>
        </div>
      ` : ""}

      ${deja_vote ? `
        <div style="margin-top:18px">
          <button class="btn btn_outline" onclick="voirResultats(${el.id})">
            <i class="fa-solid fa-chart-bar"></i> Voir les résultats
          </button>
        </div>
      ` : ""}

      ${el.phase === "vote_ferme" || el.phase === "archivee" ? `
        <div style="margin-top:18px">
          <button class="btn btn_primary" onclick="voirResultats(${el.id})">
            <i class="fa-solid fa-chart-bar"></i> Voir les résultats
          </button>
        </div>
      ` : ""}
    </div>
  `;
}

// render card candidat 
function renderCandCard(cand, can_vote = false) {
  const photo_html = cand.photo
    ? `<img src="${esc(cand.photo)}" alt="${esc(cand.nom_complet)}" style="width:100%;height:100%;object-fit:cover" loading="lazy">`
    : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:800;color:var(--blue)">${esc(cand.nom_complet.split(" ").map(w => w[0]).slice(0,2).join(""))}</div>`;

  const selectable = can_vote ? `onclick="selectionnerCand(${cand.id}, '${esc(cand.nom_complet)}')"` : "";

  return `
    <div class="cand_card" id="cand_${cand.id}" ${selectable}>
      <div class="cand_photo_wrap" onclick="event.stopPropagation();${cand.photo ? `openLightbox('${esc(cand.photo)}','${esc(cand.nom_complet)}')` : ""}">
        ${photo_html}
      </div>
      <div class="cand_name">${esc(cand.nom_complet)}</div>
      <div class="cand_slogan">"${esc(cand.slogan || "-")}"</div>
      <div class="cand_badges">
        ${cand.ufr_nom     ? `<span class="cand_badge">${esc(cand.ufr_nom)}</span>` : ""}
        ${cand.filiere_nom ? `<span class="cand_badge">${esc(cand.filiere_nom)}</span>` : ""}
        ${cand.niveau      ? `<span class="cand_badge">${esc(cand.niveau)}</span>` : ""}
      </div>
      <button class="cand_view_btn" onclick="event.stopPropagation();voirProfilCand(${cand.id})">
        <i class="fa-solid fa-eye"></i> Voir profil
      </button>
    </div>
  `;
}

// sélectionner un candidat 
function selectionnerCand(cand_id, nom) {
  candidature_choisie = cand_id;

  // reset toutes les cartes
  document.querySelectorAll(".cand_card").forEach(c => c.classList.remove("selected"));
  // sélectionner la bonne
  const card = document.getElementById(`cand_${cand_id}`);
  if (card) card.classList.add("selected");

  // activer le bouton voter
  const btn       = document.getElementById("btn_voter");
  const btn_text  = document.getElementById("btn_voter_text");
  if (btn) {
    btn.disabled = false;
  }
  if (btn_text) {
    btn_text.textContent = `Confirmer mon vote pour ${nom}`;
  }

  // step 1 done, step 2 active
  updateVoteStep(2);
}

// mettre à jour les steps 
function updateVoteStep(step) {
  vote_step = step;
  for (let i = 1; i <= 3; i++) {
    const el = document.getElementById(`step_${i}`);
    if (!el) continue;
    el.classList.remove("done", "active");
    if (i < step) el.classList.add("done");
    else if (i === step) el.classList.add("active");
  }
}

// confirmer le vote 
function confirmerVote() {
  if (!candidature_choisie || !election_courante) return;

  const cand_el   = document.getElementById(`cand_${candidature_choisie}`);
  const cand_nom  = cand_el?.querySelector(".cand_name")?.textContent || "ce candidat";

  // modal confirmation
  document.getElementById("confirmModalTitle").textContent = "Confirmer votre vote";
  document.getElementById("confirmModalMessage").innerHTML = `
    <div class="alert alert_info" style="margin-bottom:14px">
      <i class="fa-solid fa-circle-info"></i>
      <div>Ce vote est <strong>définitif et anonyme</strong>. Il ne pourra pas être modifié une fois confirmé.</div>
    </div>
    <p style="font-size:14px;text-align:center;margin-bottom:4px">Vous votez pour :</p>
    <p style="font-size:18px;font-weight:800;text-align:center;color:var(--navy)">${esc(cand_nom)}</p>
  `;
  document.getElementById("confirmModalInputWrap").style.display = "none";

  const confirm_btn = document.getElementById("confirmModalBtn");
  confirm_btn.textContent = "Confirmer mon vote";
  confirm_btn.className   = "btn btn_primary";
  confirm_btn.onclick     = async () => {
    closeModal("confirmModal");
    await soumettreVote();
  };

  openModal("confirmModal");
}

// soumettre le vote 
async function soumettreVote() {
  if (!candidature_choisie || !election_courante) return;

  const btn = document.getElementById("btn_voter");
  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...`; }

  const data = await api("api/votes.php", {
    method: "POST",
    body: JSON.stringify({
      election_id:    election_courante.id,
      candidature_id: candidature_choisie,
      tour:           election_courante.tour || 1
    })
  });

  if (data.success) {
    updateVoteStep(3);
    toast("🎉 Vote enregistré avec succès !", "success", 5000);

    // afficher message succès inline
    const cta_bar = document.getElementById("vote_cta_bar");
    if (cta_bar) {
      cta_bar.innerHTML = `
        <div class="alert alert_success" style="max-width:500px;margin:0 auto">
          <i class="fa-solid fa-circle-check"></i>
          <div>
            <strong>Vote enregistré ! Merci de votre participation.</strong><br>
            <button class="btn btn_success btn_sm" style="margin-top:8px" onclick="voirResultats(${election_courante.id})">
              <i class="fa-solid fa-chart-bar"></i> Voir les résultats
            </button>
          </div>
        </div>
      `;
    }
  } else {
    if (data.message && data.message.includes("autre appareil")) {
      // cas vote multi-session
      toast(data.message, "warning", 6000);
      setTimeout(() => voirResultats(election_courante.id), 2000);
    } else {
      toast(data.message || "Erreur lors du vote.", "error");
      if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-vote-yea"></i> Confirmer mon vote`; }
    }
  }
}

// voir profil candidat (modal) 
async function voirProfilCand(cand_id) {
  const data = await api(`api/candidatures.php?id=${cand_id}`);
  const cand = data.candidature;
  if (!cand) { toast("Candidat introuvable.", "error"); return; }

  document.getElementById("adminModalTitle").textContent = cand.nom_complet;
  document.getElementById("adminModalBody").innerHTML = `
    <div class="cand_detail_header" style="display:flex;gap:16px;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap">
      ${cand.photo ? `
        <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;border:2px solid var(--border);flex-shrink:0;cursor:zoom-in"
             onclick="openLightbox('${esc(cand.photo)}','${esc(cand.nom_complet)}')">
          <img src="${esc(cand.photo)}" alt="${esc(cand.nom_complet)}" style="width:100%;height:100%;object-fit:cover">
        </div>
      ` : `
        <div style="width:80px;height:80px;border-radius:50%;background:var(--blue-10);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:var(--blue);flex-shrink:0">
          ${esc(cand.nom_complet.split(" ").map(w => w[0]).slice(0,2).join(""))}
        </div>
      `}
      <div style="flex:1">
        <div style="font-size:1rem;font-weight:700;color:var(--text);margin-bottom:4px">${esc(cand.nom_complet)}</div>
        <div style="font-size:12px;color:var(--text-2);font-style:italic;margin-bottom:8px">"${esc(cand.slogan || "-")}"</div>
        <div style="display:flex;gap:4px;flex-wrap:wrap">
          ${cand.ufr_nom     ? `<span class="cand_badge">${esc(cand.ufr_nom)}</span>` : ""}
          ${cand.filiere_nom ? `<span class="cand_badge">${esc(cand.filiere_nom)}</span>` : ""}
          ${cand.niveau      ? `<span class="cand_badge">${esc(cand.niveau)}</span>` : ""}
        </div>
      </div>
    </div>
    ${cand.description ? `
      <div class="section_label">Présentation</div>
      <p style="font-size:13px;color:var(--text-2);line-height:1.6;margin-bottom:14px">${esc(cand.description)}</p>
    ` : ""}
    ${cand.programme ? `
      <a href="${esc(cand.programme)}" download class="btn btn_outline btn_sm">
        <i class="fa-solid fa-file-pdf"></i> Télécharger le programme PDF
      </a>
    ` : ""}
  `;

  document.getElementById("adminModalFooter").innerHTML = `
    <button class="btn btn_outline" onclick="closeModal('adminModal')">Fermer</button>
    ${election_courante?.phase === "vote" && !election_courante?.deja_vote ? `
      <button class="btn btn_primary" onclick="closeModal('adminModal');selectionnerCand(${cand.id},'${esc(cand.nom_complet)}')">
        <i class="fa-solid fa-vote-yea"></i> Voter pour ce candidat
      </button>
    ` : ""}
  `;

  openModal("adminModal");
}

// voir résultats 
async function voirResultats(election_id) {
  showPage("resultats");
  await loadResultats(election_id);
}

// page résultats 
async function loadResultats(election_id = null) {
  const container = document.getElementById("page_resultats");
  if (!container) return;

  if (!election_id) {
    // afficher sélecteur
    const data = await api("api/elections.php");
    const elections = (data.elections || []).filter(e =>
      ["vote", "vote_ferme", "archivee"].includes(e.phase)
    );

    container.innerHTML = `
      <div class="hero" style="padding:18px 20px">
        <div class="hero_inner">
          <div class="hero_label"><i class="fa-solid fa-chart-bar"></i> Résultats</div>
          <h1>Résultats des <span>élections</span></h1>
        </div>
      </div>
      <div class="page_content">
        <div class="form_group">
          <label class="form_label">Sélectionner une élection</label>
          <select class="select" onchange="if(this.value)loadResultats(this.value)">
            <option value="">-- Choisir une élection --</option>
            ${elections.map(e => `<option value="${e.id}">${esc(e.titre)} (${e.phase})</option>`).join("")}
          </select>
        </div>
        ${emptyState("fa-chart-bar", "Sélectionnez une élection", "Les résultats s'afficheront ici.")}
      </div>
    `;
    return;
  }

  container.innerHTML = `
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div class="skeleton" style="height:18px;width:40%;border-radius:6px;background:rgba(255,255,255,0.1)"></div>
      </div>
    </div>
    <div class="page_content">${skeletonCards(3, "60px")}</div>
  `;

  const data = await api(`api/votes.php?action=results&election_id=${election_id}`);

  if (!data.success) {
    container.innerHTML = `
      <div class="page_content">
        <div class="alert alert_info"><i class="fa-solid fa-lock"></i> <span>${data.message || "Résultats non disponibles."}</span></div>
      </div>
    `;
    return;
  }

  const el         = data.election || {};
  const resultats  = data.resultats || [];
  const total      = data.total_votes || 0;
  const participation = data.participation || {};
  const gagnant    = resultats[0];

  container.innerHTML = `
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          ${phaseTag(el.phase, el.tour)}
        </div>
        <h1 style="font-size:clamp(1rem,2.5vw,1.4rem)">${esc(el.titre || "Résultats")}</h1>
        <div style="font-size:11px;color:rgba(255,255,255,0.45);margin-top:4px">
          ${scopeLabel(el)} · Tour ${el.tour || 1}
        </div>
        <div class="hero_stats" style="margin-top:16px">
          <div class="hero_stat">
            <div class="hero_stat_val">${participation.votants || 0}</div>
            <div class="hero_stat_lbl">Votants</div>
          </div>
          <div class="hero_stat">
            <div class="hero_stat_val">${participation.eligibles || 0}</div>
            <div class="hero_stat_lbl">Éligibles</div>
          </div>
          <div class="hero_stat">
            <div class="hero_stat_val">${participation.taux || 0}<span>%</span></div>
            <div class="hero_stat_lbl">Participation</div>
          </div>
        </div>
      </div>
    </div>
    <div class="page_content">
      ${gagnant && (el.phase === "vote_ferme" || el.phase === "archivee") ? `
        <div class="winner_banner">
          <h4>Gagnant</h4>
          <div class="winner_name">🏆 ${esc(gagnant.nom_complet)}</div>
          <div class="winner_votes">${gagnant.nb_votes} voix · ${gagnant.pourcentage}%</div>
          ${gagnant.decision_admin ? `<div style="font-size:10px;color:rgba(255,255,255,0.4);margin-top:4px">Élu par décision administrative</div>` : ""}
        </div>
      ` : ""}

      <div class="section_label">Classement des candidats</div>
      <div class="result_list">
        ${resultats.map((r, i) => `
          <div class="result_item ${i === 0 && total > 0 ? "winner" : ""}">
            <div class="result_row">
              <div class="result_name">${i === 0 && total > 0 ? "🏆 " : ""}${esc(r.nom_complet)}</div>
              <div class="result_votes">${r.nb_votes} vote(s) · ${r.pourcentage}%</div>
            </div>
            <div class="result_bar_wrap">
              <div class="result_bar" style="width:${r.pourcentage}%"></div>
            </div>
          </div>
        `).join("")}
      </div>

      ${(el.phase === "vote_ferme" || el.phase === "archivee") ? `
        <div style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn_outline btn_sm" onclick="exporterPDF(${el.id})">
            <i class="fa-solid fa-file-pdf"></i> Export PDF
          </button>
          <button class="btn btn_outline btn_sm" onclick="exporterCSV(${el.id})">
            <i class="fa-solid fa-file-csv"></i> Export CSV
          </button>
        </div>
      ` : ""}
    </div>
  `;
}

// exporter PDF/CSV 
function exporterPDF(election_id) {
  window.open(`api/votes.php?action=export_pdf&election_id=${election_id}`, "_blank");
}

function exporterCSV(election_id) {
  window.open(`api/votes.php?action=export_csv&election_id=${election_id}`, "_blank");
}

// page toutes les élections 
async function loadElections() {
  const container = document.getElementById("page_elections_content");
  if (!container) return;

  container.innerHTML = `
    <div class="hero" style="padding:18px 20px">
      <div class="hero_inner">
        <div class="hero_label"><i class="fa-solid fa-ballot-check"></i> Élections</div>
        <h1>Toutes les <span>élections</span></h1>
      </div>
    </div>
    <div class="page_content">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
        ${skeletonCards(6, "220px")}
      </div>
    </div>
  `;

  const data = await api("api/elections.php");
  const elections = data.elections || [];

  const hero_part = container.querySelector(".hero");
  const content   = document.createElement("div");
  content.className = "page_content";

  // filtres chips
  content.innerHTML = `
    <div class="filter_bar" style="margin-bottom:16px;padding:8px 0;background:none;border:none">
      <span class="filter_label">Phase :</span>
      <span class="chip active" onclick="filtrerElections(this,'toutes')">Toutes (${elections.length})</span>
      <span class="chip" onclick="filtrerElections(this,'vote')">🟢 Vote (${elections.filter(e=>e.phase==="vote").length})</span>
      <span class="chip" onclick="filtrerElections(this,'candidatures')">🟡 Candidatures (${elections.filter(e=>e.phase==="candidatures").length})</span>
      <span class="chip" onclick="filtrerElections(this,'archivee')">⚫ Archivées (${elections.filter(e=>e.phase==="archivee").length})</span>
    </div>
    <div id="elections_grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
      ${elections.length === 0
        ? emptyState("fa-ballot-check", "Aucune élection", "")
        : elections.map(e => renderElectionCard(e)).join("")
      }
    </div>
  `;

  container.innerHTML = "";
  if (hero_part) container.appendChild(hero_part);
  container.appendChild(content);
}

let elections_all = [];
function filtrerElections(chip_el, phase) {
  document.querySelectorAll(".chip").forEach(c => c.classList.remove("active"));
  chip_el.classList.add("active");

  const grid    = document.getElementById("elections_grid");
  if (!grid) return;

  const data    = elections_cache;
  const filtered = phase === "toutes" ? data : data.filter(e => e.phase === phase);
  grid.innerHTML = filtered.length === 0
    ? emptyState("fa-ballot-check", "Aucune élection", `Pas d'élection en phase "${phase}".`)
    : filtered.map(e => renderElectionCard(e)).join("");
}

// ouvrir formulaire candidature 
async function ouvrirCandidature(election_id) {
  showPage("candidature");
  const page = document.getElementById("page_candidature");
  if (!page) return;
  page.dataset.election_id = election_id;
  // charger l'election pour afficher le titre
  const data = await api(`api/elections.php?id=${election_id}`);
  if (data.success) {
    const titre_el = document.getElementById("cand_form_election_titre");
    if (titre_el) titre_el.textContent = data.election.titre;
  }
}

// exposer au scope global 
window.loadAccueil       = loadAccueil;
window.loadElections     = loadElections;
window.loadResultats     = loadResultats;
window.ouvrirElection    = ouvrirElection;
window.ouvrirCandidature = ouvrirCandidature;
window.selectionnerCand  = selectionnerCand;
window.confirmerVote     = confirmerVote;
window.voirProfilCand    = voirProfilCand;
window.voirResultats     = voirResultats;
window.exporterPDF       = exporterPDF;
window.exporterCSV       = exporterCSV;
window.filtrerElections  = filtrerElections;
