/**
 * votenow v4 - module admin (direction 2 - command)
 * sidebar + tableau dense + filtres chips + pagination + alertes
 * signature: 2-space indent, camelCase fonctions, snake_case vars locales */

"use strict";

// état local admin 
let admin_tab_courant   = "dashboard";
let elections_page      = 1;
let etudiants_page      = 1;
let logs_page           = 1;
let elections_filtre    = "toutes";
let cands_filtre        = "toutes";
let logs_filtre         = "tous";
const PER_PAGE          = 50;

// init admin layout 
function initAdminLayout() {
  if (!window.SESSION_ADMIN) return;
  renderSidebar();
  loadAdminDashboard();
}

// sidebar 
function renderSidebar() {
  const sb = document.getElementById("sidebar");
  if (!sb) return;

  const is_super = window.SESSION_ADMIN?.role === "superadmin";

  const items_elections = [
    { id: "dashboard",    icon: "fa-gauge",          label: "Tableau de bord" },
    { id: "elections",    icon: "fa-ballot-check",   label: "Élections",    badge_id: "sb_badge_elections" },
    { id: "candidatures", icon: "fa-file-signature", label: "Candidatures", badge_id: "sb_badge_cands", badge_color: "red" },
    { id: "resultats",    icon: "fa-chart-bar",      label: "Résultats" },
  ];

  const items_config = [
    ...(is_super ? [
      { id: "structure",  icon: "fa-building-columns", label: "Structure" },
      { id: "etudiants",  icon: "fa-graduation-cap",   label: "Étudiants" },
      { id: "admins",     icon: "fa-user-shield",      label: "Admins" },
      { id: "demandes",   icon: "fa-inbox",             label: "Demandes",  badge_id: "sb_badge_demandes", badge_color: "yellow" },
      { id: "logs",       icon: "fa-scroll",            label: "Logs" },
      { id: "parametres", icon: "fa-gear",              label: "Paramètres" },
    ] : [
      { id: "logs",       icon: "fa-scroll",            label: "Logs" },
    ])
  ];

  const renderItems = (items) => items.map(item => `
    <button class="sb_item ${item.id === admin_tab_courant ? "active" : ""}"
            onclick="loadAdminTab('${item.id}')"
            data-tab="${item.id}">
      <span class="sb_item_icon"><i class="fa-solid ${item.icon}"></i></span>
      <span class="sb_item_name">${item.label}</span>
      ${item.badge_id ? `<span class="sb_badge ${item.badge_color || ""}" id="${item.badge_id}" style="display:none">0</span>` : ""}
    </button>
  `).join("");

  sb.innerHTML = `
    <div class="sb_section">
      <span class="sb_section_lbl">Élections</span>
      ${renderItems(items_elections)}
    </div>
    <div class="sb_section">
      <span class="sb_section_lbl">Configuration</span>
      ${renderItems(items_config)}
    </div>
    <div class="sb_footer">
      <div class="sb_user">
        <div class="avatar" style="width:26px;height:26px;font-size:9px">
          ${esc((window.SESSION_ADMIN?.username || "?")[0].toUpperCase())}
        </div>
        <div class="sb_user_info">
          <div class="sb_user_name">${esc(window.SESSION_ADMIN?.username || "")}</div>
          <div class="sb_user_role">${esc(window.SESSION_ADMIN?.role === "superadmin" ? "Super Admin" : "Admin")}</div>
        </div>
      </div>
    </div>
  `;
}

// charger onglet admin 
async function loadAdminTab(tab_id) {
  admin_tab_courant = tab_id;

  // update sidebar active
  document.querySelectorAll(".sb_item").forEach(el => {
    el.classList.toggle("active", el.dataset.tab === tab_id);
  });

  const content = document.getElementById("admin_content");
  const topbar  = document.getElementById("admin_topbar_title");
  const filters = document.getElementById("admin_filter_bar");

  if (!content) return;

  // reset filter bar
  if (filters) filters.innerHTML = "";

  const titles = {
    dashboard:    "Tableau de bord",
    elections:    "Élections",
    candidatures: "Candidatures",
    resultats:    "Résultats",
    structure:    "Structure universitaire",
    etudiants:    "Étudiants",
    admins:       "Administrateurs",
    demandes:     "Demandes de profil",
    logs:         "Journal d'activité",
    parametres:   "Paramètres"
  };

  if (topbar) topbar.textContent = titles[tab_id] || tab_id;

  content.innerHTML = `<div style="display:grid;gap:12px;padding-top:4px">${skeletonCards(3, "60px")}</div>`;

  switch (tab_id) {
    case "dashboard":    await loadAdminDashboard(); break;
    case "elections":    await loadAdminElections(); break;
    case "candidatures": await loadAdminCandidatures(); break;
    case "resultats":    await loadAdminResultats(); break;
    case "structure":    await loadAdminStructure(); break;
    case "etudiants":    await loadAdminEtudiants(); break;
    case "admins":       await loadAdminAdmins(); break;
    case "demandes":     await loadAdminDemandes(); break;
    case "logs":         await loadAdminLogs(); break;
    case "parametres":   await loadAdminParametres(); break;
  }
}

// dashboard 
async function loadAdminDashboard() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const data = await api("api/admin.php?section=dashboard");
  const s    = data.stats || {};
  const alertes = data.alertes || [];
  const activite = data.activite_recente || [];

  // mettre à jour badges sidebar
  updateSidebarBadge("sb_badge_cands",    s.candidatures_attente || 0);
  updateSidebarBadge("sb_badge_demandes", s.demandes_attente || 0);

  // alert bar
  const alert_bar = document.getElementById("admin_alert_bar");
  if (alert_bar) {
    if (alertes.length > 0) {
      alert_bar.style.display = "flex";
      alert_bar.innerHTML = `
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>${alertes.map(a => esc(a.message)).join(" · ")}</span>
      `;
    } else {
      alert_bar.style.display = "none";
    }
  }

  content.innerHTML = `
    <!-- stats grid -->
    <div class="stats_grid" style="margin-bottom:20px">
      <div class="stat_card">
        <div class="stat_icon"><i class="fa-solid fa-ballot-check"></i></div>
        <div class="stat_content">
          <div class="stat_value">${s.elections_actives || 0}</div>
          <div class="stat_label">Élections actives</div>
        </div>
      </div>
      <div class="stat_card">
        <div class="stat_icon" style="background:var(--green-10);color:var(--green)"><i class="fa-solid fa-vote-yea"></i></div>
        <div class="stat_content">
          <div class="stat_value">${s.total_votes || 0}</div>
          <div class="stat_label">Votes exprimés</div>
        </div>
      </div>
      <div class="stat_card" onclick="loadAdminTab('candidatures')" style="cursor:pointer">
        <div class="stat_icon" style="background:var(--yellow-10);color:var(--yellow)"><i class="fa-solid fa-file-signature"></i></div>
        <div class="stat_content">
          <div class="stat_value">${s.candidatures_attente || 0}</div>
          <div class="stat_label">Candidatures en attente</div>
          ${s.candidatures_attente > 0 ? `<div class="stat_trend up"><i class="fa-solid fa-arrow-up"></i> Nécessite action</div>` : ""}
        </div>
      </div>
      <div class="stat_card">
        <div class="stat_icon" style="background:var(--purple-10);color:var(--purple)"><i class="fa-solid fa-graduation-cap"></i></div>
        <div class="stat_content">
          <div class="stat_value">${s.total_etudiants || 0}</div>
          <div class="stat_label">Étudiants inscrits</div>
        </div>
      </div>
    </div>

    <div class="grid_2" style="gap:16px">
      <!-- participation par élection -->
      <div class="card">
        <div class="card_header">
          <div class="card_title"><i class="fa-solid fa-chart-bar"></i> Participation en cours</div>
        </div>
        <div class="card_body">
          ${(s.elections_en_vote || []).length === 0
            ? `<div class="text_xs text_2" style="padding:8px 0">Aucune élection en vote actuellement.</div>`
            : `<div class="mini_bar_chart">
                ${(s.elections_en_vote || []).map(el => `
                  <div class="mini_bar_row">
                    <span class="mini_bar_label" title="${esc(el.titre)}">${esc(el.titre)}</span>
                    <div class="mini_bar_track">
                      <div class="mini_bar_fill" style="width:${el.pct || 0}%"></div>
                    </div>
                    <span class="mini_bar_val">${el.pct || 0}%</span>
                  </div>
                `).join("")}
              </div>`
          }
        </div>
      </div>

      <!-- fil d'activité -->
      <div class="card">
        <div class="card_header">
          <div class="card_title"><i class="fa-solid fa-clock-rotate-left"></i> Activité récente</div>
          <button class="btn btn_ghost btn_sm" onclick="loadAdminTab('logs')">Tout voir</button>
        </div>
        <div class="activity_list">
          ${activite.length === 0
            ? `<div class="empty_state" style="padding:24px"><i class="fa-solid fa-clock"></i><h3>Aucune activité</h3></div>`
            : activite.slice(0, 8).map(log => `
                <div class="activity_item">
                  <div class="activity_dot" style="background:${logColor(log.type)}"></div>
                  <div class="activity_text">
                    <span>${esc(log.action)}</span>
                  </div>
                  <div class="activity_time">${timeAgo(log.created_at)}</div>
                </div>
              `).join("")
          }
        </div>
      </div>
    </div>
  `;
}

function logColor(type) {
  const colors = {
    login: "#3b82f6", login_admin: "#8b5cf6", vote: "#16a34a",
    candidature: "#ca8a04", login_failed: "#dc2626", register: "#06b6d4"
  };
  return colors[type] || "#94a3b8";
}

function updateSidebarBadge(badge_id, count) {
  const el = document.getElementById(badge_id);
  if (!el) return;
  if (count > 0) {
    el.textContent   = count;
    el.style.display = "flex";
  } else {
    el.style.display = "none";
  }
}

// elections admin 
async function loadAdminElections(page = 1) {
  elections_page = page;
  const content  = document.getElementById("admin_content");
  const filters  = document.getElementById("admin_filter_bar");
  if (!content) return;

  // filter bar
  if (filters) {
    filters.innerHTML = `
      <span class="filter_label">Phase :</span>
      ${["toutes","vote","candidatures","vote_ferme","archivee","brouillon"].map(f => `
        <span class="chip ${elections_filtre === f ? "active" : ""}"
              onclick="setElectionFiltre('${f}',this)">
          ${f === "toutes" ? "Toutes" : f.replace("_"," ")}
        </span>
      `).join("")}
      <div class="filter_search" style="margin-left:auto">
        <input class="input" style="width:180px;padding:5px 10px;font-size:11px"
               placeholder="🔍 Rechercher..."
               id="elections_search_input"
               oninput="rechercherElections(this.value)">
      </div>
      <button class="btn btn_primary btn_sm" onclick="openModalCreerElection()">
        <i class="fa-solid fa-plus"></i> Créer
      </button>
    `;
  }

  const params = elections_filtre !== "toutes" ? `&phase=${elections_filtre}` : "";
  const data   = await api(`api/elections.php?admin=1&page=${page}&per_page=${PER_PAGE}${params}`);
  const elections = data.elections || [];
  const total     = data.total || elections.length;

  if (!elections.length) {
    content.innerHTML = emptyState(
      "fa-ballot-check",
      "Aucune élection",
      "Créez votre première élection.",
      `<button class="btn btn_primary" onclick="openModalCreerElection()"><i class="fa-solid fa-plus"></i> Créer une élection</button>`
    );
    return;
  }

  content.innerHTML = `
    <div class="table_wrap">
      <table>
        <thead>
          <tr>
            <th>Titre</th>
            <th>Phase</th>
            <th>Périmètre</th>
            <th>Participation</th>
            <th>Tour</th>
            <th>Créée le</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="elections_tbody">
          ${elections.map(el => renderElectionRow_admin(el)).join("")}
        </tbody>
      </table>
      ${renderPager(total, page, PER_PAGE, "loadAdminElections")}
    </div>
  `;
}

function renderElectionRow_admin(el) {
  const nb_votants  = parseInt(el.nb_votes || 0);
  const nb_eligible = parseInt(el.nb_eligibles || 0);

  return `
    <tr>
      <td style="font-weight:500;max-width:220px">
        <div class="truncate" title="${esc(el.titre)}">${esc(el.titre)}</div>
      </td>
      <td>${phaseTag(el.phase, el.tour)}</td>
      <td style="color:var(--text-2);font-size:11px">${scopeLabel(el)}</td>
      <td>${partBar(nb_votants, nb_eligible)}</td>
      <td>
        ${el.tour > 1
          ? `<span style="background:var(--blue-pale);color:var(--blue-hover);font-size:9px;font-weight:600;padding:2px 6px;border-radius:3px">T${el.tour}</span>`
          : `<span style="color:var(--text-3);font-size:11px">T1</span>`
        }
      </td>
      <td style="color:var(--text-3);font-size:11px">${formatDate(el.created_at)}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn_outline btn_sm" onclick="gererElection(${el.id})">Gérer</button>
          <button class="btn btn_danger btn_sm" onclick="supprimerElection(${el.id},'${esc(el.titre)}')">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>
  `;
}

function setElectionFiltre(filtre, chip_el) {
  elections_filtre = filtre;
  document.querySelectorAll("#admin_filter_bar .chip").forEach(c => c.classList.remove("active"));
  chip_el.classList.add("active");
  loadAdminElections(1);
}

function rechercherElections(q) {
  const tbody = document.getElementById("elections_tbody");
  if (!tbody) return;
  const rows = tbody.querySelectorAll("tr");
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q.toLowerCase()) ? "" : "none";
  });
}

// supprimer élection avec re-saisie 
function supprimerElection(id, titre) {
  openConfirmModal({
    title:         "Supprimer cette élection",
    message:       `<div class="alert alert_danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Cette action supprimera <strong>toutes les candidatures et votes</strong> associés. Action irréversible.</div></div>`,
    target_name:   titre,
    confirm_label: "Supprimer définitivement",
    danger:        true,
    callback:      async () => {
      const data = await api(`api/elections.php`, {
        method: "DELETE",
        body: JSON.stringify({ id })
      });
      if (data.success) {
        toast("Élection supprimée.", "success");
        loadAdminElections(elections_page);
      } else {
        toast(data.message || "Erreur lors de la suppression.", "error");
      }
    }
  });
}

// gérer une élection 
async function gererElection(election_id) {
  const data = await api(`api/elections.php?id=${election_id}`);
  if (!data.success) { toast("Élection introuvable.", "error"); return; }
  const el = data.election;

  document.getElementById("adminModalTitle").textContent = el.titre;
  document.getElementById("adminModalBody").innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      ${phaseTag(el.phase, el.tour)}
      <span class="text_xs text_2">${scopeLabel(el)}</span>
    </div>

    <div class="form_group">
      <label class="form_label">Changer la phase</label>
      <select class="select" id="phase_select_modal">
        ${["brouillon","candidatures","candidatures_fermees","vote","vote_ferme","archivee"].map(p => `
          <option value="${p}" ${p === el.phase ? "selected" : ""}>${p.replace("_"," ")}</option>
        `).join("")}
      </select>
    </div>

    ${el.phase === "vote_ferme" ? `
      <div class="alert alert_info" style="margin-bottom:12px">
        <i class="fa-solid fa-circle-info"></i>
        <span>Si égalité, vous pouvez lancer le 2ème tour ou prendre une décision administrative.</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn_blue btn_sm" onclick="lancerTour2(${el.id})">
          <i class="fa-solid fa-bolt"></i> Lancer 2ème tour
        </button>
        <button class="btn btn_outline btn_sm" onclick="closeModal('adminModal');decisionAdmin(${el.id})">
          <i class="fa-solid fa-gavel"></i> Décision administrative
        </button>
      </div>
    ` : ""}
  `;

  document.getElementById("adminModalFooter").innerHTML = `
    <button class="btn btn_outline" onclick="closeModal('adminModal')">Annuler</button>
    <button class="btn btn_primary" onclick="changerPhase(${el.id})">
      <i class="fa-solid fa-check"></i> Appliquer
    </button>
  `;

  openModal("adminModal");
}

async function changerPhase(election_id) {
  const phase = document.getElementById("phase_select_modal")?.value;
  if (!phase) return;
  const data = await api("api/elections.php", {
    method: "POST",
    body: JSON.stringify({ action: "phase", election_id, phase })
  });
  if (data.success) {
    toast("Phase mise à jour.", "success");
    closeModal("adminModal");
    loadAdminElections(elections_page);
  } else {
    toast(data.message || "Erreur.", "error");
  }
}

async function lancerTour2(election_id) {
  const data = await api("api/elections.php", {
    method: "POST",
    body: JSON.stringify({ action: "tour2", election_id })
  });
  if (data.success) {
    toast("2ème tour lancé avec succès !", "success");
    closeModal("adminModal");
    loadAdminElections(elections_page);
  } else {
    toast(data.message || "Impossible de lancer le 2ème tour.", "error");
  }
}

function decisionAdmin(election_id) {
  openMotifModal(
    "Décision administrative",
    "Expliquez la décision (ex: plus grand nombre de voix au tour 1...)",
    async (motif) => {
      // sélectionner le candidat élu
      const cands_data = await api(`api/candidatures.php?election_id=${election_id}`);
      const cands      = (cands_data.candidatures || []).filter(c => c.qualifie_tour2 || c.statut === "validee");

      document.getElementById("adminModalTitle").textContent = "Désigner le gagnant";
      document.getElementById("adminModalBody").innerHTML = `
        <div class="form_group">
          <label class="form_label">Candidat élu</label>
          <select class="select" id="decision_cand_select">
            <option value="">-- Sélectionner --</option>
            ${cands.map(c => `<option value="${c.id}">${esc(c.nom_complet)}</option>`).join("")}
          </select>
        </div>
        <div class="alert alert_info">
          <i class="fa-solid fa-circle-info"></i>
          <span>Motif saisi : <em>${esc(motif)}</em></span>
        </div>
      `;
      document.getElementById("adminModalFooter").innerHTML = `
        <button class="btn btn_outline" onclick="closeModal('adminModal')">Annuler</button>
        <button class="btn btn_primary" onclick="soumettreDecision(${election_id},'${esc(motif)}')">
          <i class="fa-solid fa-gavel"></i> Confirmer
        </button>
      `;
      openModal("adminModal");
    }
  );
}

async function soumettreDecision(election_id, motif) {
  const cand_id = document.getElementById("decision_cand_select")?.value;
  if (!cand_id) { toast("Sélectionnez un candidat.", "warning"); return; }
  const data = await api("api/elections.php", {
    method: "POST",
    body: JSON.stringify({ action: "decision", election_id, candidature_id: cand_id, motif })
  });
  if (data.success) {
    toast("Décision enregistrée.", "success");
    closeModal("adminModal");
    loadAdminElections(elections_page);
  } else {
    toast(data.message || "Erreur.", "error");
  }
}

// candidatures admin 
async function loadAdminCandidatures(page = 1) {
  const content = document.getElementById("admin_content");
  const filters = document.getElementById("admin_filter_bar");
  if (!content) return;

  if (filters) {
    filters.innerHTML = `
      <span class="filter_label">Statut :</span>
      ${["toutes","en_attente","validee","refusee"].map(f => `
        <span class="chip ${cands_filtre === f ? "active" : ""}"
              onclick="setCandFiltre('${f}',this)">
          ${f === "toutes" ? "Toutes" : f.replace("_"," ")}
        </span>
      `).join("")}
      <button class="btn btn_primary btn_sm" style="margin-left:auto" onclick="openModalAjouterCand()">
        <i class="fa-solid fa-plus"></i> Ajouter
      </button>
    `;
  }

  const params = cands_filtre !== "toutes" ? `&statut=${cands_filtre}` : "";
  const data   = await api(`api/candidatures.php?admin=1&page=${page}&per_page=${PER_PAGE}${params}`);
  const cands  = data.candidatures || [];
  const total  = data.total || cands.length;

  // update badge
  updateSidebarBadge("sb_badge_cands", data.total_attente || 0);

  if (!cands.length) {
    content.innerHTML = emptyState("fa-file-signature", "Aucune candidature", cands_filtre !== "toutes" ? `Aucune candidature "${cands_filtre}".` : "");
    return;
  }

  content.innerHTML = `
    <div class="table_wrap">
      <table>
        <thead>
          <tr>
            <th>Candidat</th>
            <th>Élection</th>
            <th>Statut</th>
            <th>Déposée le</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${cands.map((c, i) => `
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  ${c.photo
                    ? `<div style="width:28px;height:28px;border-radius:50%;overflow:hidden;flex-shrink:0;cursor:zoom-in" onclick="openLightbox('${esc(c.photo)}','${esc(c.nom_complet)}')"><img src="${esc(c.photo)}" style="width:100%;height:100%;object-fit:cover"></div>`
                    : `<div class="avatar" style="width:28px;height:28px;font-size:9px">${esc(c.nom_complet.split(" ").map(w=>w[0]).slice(0,2).join(""))}</div>`
                  }
                  <div>
                    <div style="font-size:12px;font-weight:500">${esc(c.nom_complet)}</div>
                    <div style="font-size:10px;color:var(--text-3)">"${esc((c.slogan||"").slice(0,40))}${(c.slogan||"").length>40?"...":""}"</div>
                  </div>
                </div>
              </td>
              <td style="font-size:11px;color:var(--text-2);max-width:180px">
                <div class="truncate">${esc(c.election_titre || "-")}</div>
              </td>
              <td>
                <span class="tag ${c.statut==="validee"?"tag_ok":c.statut==="refusee"?"tag_ko":c.statut==="en_attente"?"tag_pend":"tag_arch"}">
                  ${esc(c.statut.replace("_"," "))}
                </span>
              </td>
              <td style="color:var(--text-3);font-size:11px">${formatDate(c.created_at)}</td>
              <td>
                <div style="display:flex;gap:4px">
                  ${c.statut === "en_attente" ? `
                    <button class="btn btn_success btn_sm" onclick="validerCand(${c.id})">Valider</button>
                    <button class="btn btn_danger btn_sm" onclick="refuserCand(${c.id})">Refuser</button>
                  ` : `
                    <button class="btn btn_ghost btn_sm" onclick="voirProfilCand(${c.id})">Voir</button>
                  `}
                </div>
              </td>
            </tr>
          `).join("")}
        </tbody>
      </table>
      ${renderPager(total, page, PER_PAGE, "loadAdminCandidatures")}
    </div>
  `;
}

function setCandFiltre(filtre, chip_el) {
  cands_filtre = filtre;
  document.querySelectorAll("#admin_filter_bar .chip").forEach(c => c.classList.remove("active"));
  chip_el.classList.add("active");
  loadAdminCandidatures(1);
}

async function validerCand(cand_id) {
  const data = await api("api/candidatures.php", {
    method: "PUT",
    body: JSON.stringify({ id: cand_id, action: "valider" })
  });
  if (data.success) {
    toast("Candidature validée ✓", "success");
    loadAdminCandidatures();
  } else {
    toast(data.message || "Erreur.", "error");
  }
}

function refuserCand(cand_id) {
  openMotifModal(
    "Motif de refus",
    "Expliquez pourquoi la candidature est refusée...",
    async (motif) => {
      const data = await api("api/candidatures.php", {
        method: "PUT",
        body: JSON.stringify({ id: cand_id, action: "refuser", motif })
      });
      if (data.success) {
        toast("Candidature refusée.", "info");
        loadAdminCandidatures();
      } else {
        toast(data.message || "Erreur.", "error");
      }
    }
  );
}

// resultats admin 
async function loadAdminResultats() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const data     = await api("api/elections.php?admin=1");
  const elections = (data.elections || []).filter(e =>
    ["vote","vote_ferme","archivee"].includes(e.phase)
  );

  content.innerHTML = `
    <div class="form_group" style="max-width:400px">
      <label class="form_label">Sélectionner une élection</label>
      <select class="select" id="admin_resultats_select" onchange="chargerResultatsAdmin(this.value)">
        <option value="">-- Sélectionner --</option>
        ${elections.map(e => `<option value="${e.id}">${esc(e.titre)} (${e.phase})</option>`).join("")}
      </select>
    </div>
    <div id="admin_resultats_content">
      ${emptyState("fa-chart-bar", "Sélectionnez une élection", "")}
    </div>
  `;
}

async function chargerResultatsAdmin(election_id) {
  if (!election_id) return;
  const cont = document.getElementById("admin_resultats_content");
  if (!cont) return;
  cont.innerHTML = skeletonCards(3, "50px");

  const data = await api(`api/votes.php?action=results&election_id=${election_id}`);
  const el   = data.election || {};

  if (!data.success) {
    cont.innerHTML = `<div class="alert alert_info"><i class="fa-solid fa-lock"></i> <span>${data.message}</span></div>`;
    return;
  }

  const resultats     = data.resultats || [];
  const participation = data.participation || {};

  cont.innerHTML = `
    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:180px">
        <div class="card_body" style="text-align:center">
          <div style="font-size:1.75rem;font-weight:800;color:var(--text)">${participation.votants || 0}</div>
          <div style="font-size:11px;color:var(--text-3)">Votants / ${participation.eligibles || "?"} éligibles</div>
          <div style="font-size:1.25rem;font-weight:700;color:var(--blue);margin-top:4px">${participation.taux || 0}%</div>
          <div style="font-size:10px;color:var(--text-3)">Participation</div>
        </div>
      </div>
      <div style="flex:2;min-width:240px">
        <div class="result_list">
          ${resultats.map((r, i) => `
            <div class="result_item ${i===0?"winner":""}">
              <div class="result_row">
                <div class="result_name">${i===0?"🏆 ":""}${esc(r.nom_complet)}</div>
                <div class="result_votes">${r.nb_votes} voix · ${r.pourcentage}%</div>
              </div>
              <div class="result_bar_wrap">
                <div class="result_bar" style="width:${r.pourcentage}%"></div>
              </div>
            </div>
          `).join("")}
        </div>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn_outline btn_sm" onclick="exporterPDF(${el.id})">
        <i class="fa-solid fa-file-pdf"></i> PDF
      </button>
      <button class="btn btn_outline btn_sm" onclick="exporterCSV(${el.id})">
        <i class="fa-solid fa-file-csv"></i> CSV
      </button>
      ${el.phase === "vote_ferme" ? `
        <button class="btn btn_blue btn_sm" onclick="lancerTour2(${el.id})">
          <i class="fa-solid fa-bolt"></i> 2ème tour
        </button>
        <button class="btn btn_outline btn_sm" onclick="decisionAdmin(${el.id})">
          <i class="fa-solid fa-gavel"></i> Décision admin
        </button>
        <button class="btn btn_danger btn_sm" onclick="reinitialiserVotes(${el.id},'${esc(el.titre)}')">
          <i class="fa-solid fa-rotate-left"></i> Réinitialiser
        </button>
      ` : ""}
    </div>
  `;
}

function reinitialiserVotes(election_id, titre) {
  openConfirmModal({
    title:         "Réinitialiser les votes",
    message:       `<div class="alert alert_danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Cette action supprime TOUS les votes de l'élection. Irréversible. Un backup sera créé automatiquement.</div></div>`,
    target_name:   titre,
    confirm_label: "Réinitialiser",
    danger:        true,
    callback:      async () => {
      const data = await api("api/votes.php", {
        method: "DELETE",
        body: JSON.stringify({ election_id })
      });
      if (data.success) { toast("Votes réinitialisés.", "info"); chargerResultatsAdmin(election_id); }
      else toast(data.message || "Erreur.", "error");
    }
  });
}

// structure 
async function loadAdminStructure() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const [ufr_data, fil_data] = await Promise.all([
    api("api/admin.php?section=ufr"),
    api("api/admin.php?section=filieres")
  ]);

  const ufr_options = (ufr_data.ufr || []).map(u =>
    `<option value="${u.id}">${esc(u.nom)} (${esc(u.code)})</option>`
  ).join("");

  content.innerHTML = `
    <div class="grid_2" style="gap:18px">
      <!-- UFR -->
      <div class="card">
        <div class="card_header">
          <div class="card_title"><i class="fa-solid fa-building-columns"></i> UFR</div>
        </div>
        <div class="card_body">
          <div id="ufr_alert"></div>
          <div class="form_group">
            <label class="form_label">Nom <span class="form_required">*</span></label>
            <input type="text" id="ufr_nom_input" class="input" placeholder="Ex: UFR Sciences de la Santé">
          </div>
          <div class="form_group">
            <label class="form_label">Code <span class="form_required">*</span></label>
            <input type="text" id="ufr_code_input" class="input" placeholder="Ex: UFR-SS">
          </div>
          <div class="form_group">
            <label class="form_label">Description</label>
            <textarea id="ufr_desc_input" class="textarea" rows="2" placeholder="Disciplines..."></textarea>
          </div>
          <button class="btn btn_primary btn_full" onclick="creerUfr()">
            <i class="fa-solid fa-plus"></i> Créer l'UFR
          </button>
        </div>
        <div id="ufr_list" style="max-height:260px;overflow-y:auto">
          ${renderUfrList(ufr_data.ufr || [])}
        </div>
      </div>

      <!-- Filières -->
      <div class="card">
        <div class="card_header">
          <div class="card_title"><i class="fa-solid fa-list-ul"></i> Filières</div>
        </div>
        <div class="card_body">
          <div id="fil_alert"></div>
          <div class="form_group">
            <label class="form_label">UFR <span class="form_required">*</span></label>
            <select id="fil_ufr_select" class="select" onchange="onStructUfrChange(this)">
              <option value="">-- Sélectionner l'UFR --</option>
              ${ufr_options}
            </select>
          </div>
          <div class="form_group">
            <label class="form_label">Nom de la filière <span class="form_required">*</span></label>
            <input type="text" id="fil_nom_input" class="input" placeholder="Ex: Informatique">
          </div>
          <div class="form_group">
            <label class="form_label">Niveaux disponibles</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px">
              ${["L1","L2","L3","M1","M2"].map(n => `
                <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px;font-weight:500">
                  <input type="checkbox" value="${n}" class="niveau_check" style="accent-color:var(--blue);width:14px;height:14px">
                  ${n}
                </label>
              `).join("")}
            </div>
          </div>
          <button class="btn btn_primary btn_full" onclick="creerFiliere()">
            <i class="fa-solid fa-plus"></i> Créer la filière
          </button>
        </div>
        <div id="fil_list" style="max-height:260px;overflow-y:auto">
          ${renderFiliereList(fil_data.filieres || [])}
        </div>
      </div>
    </div>
  `;
}

function renderUfrList(ufrs) {
  if (!ufrs.length) return `<div class="empty_state" style="padding:20px"><i class="fa-solid fa-building-columns"></i><h3>Aucune UFR</h3></div>`;
  return ufrs.map(u => `
    <div style="padding:10px 16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px">
      <div>
        <div style="font-size:12px;font-weight:500">${esc(u.nom)}</div>
        <div style="font-size:10px;color:var(--text-3)">${esc(u.code)} · ${u.nb_filieres || 0} filière(s)</div>
      </div>
      <button class="btn btn_danger btn_sm" onclick="supprimerUfr(${u.id},'${esc(u.nom)}')">
        <i class="fa-solid fa-trash"></i>
      </button>
    </div>
  `).join("");
}

function renderFiliereList(filieres) {
  if (!filieres.length) return `<div class="empty_state" style="padding:20px"><i class="fa-solid fa-list-ul"></i><h3>Aucune filière</h3></div>`;
  return filieres.map(f => `
    <div style="padding:10px 16px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px">
      <div>
        <div style="font-size:12px;font-weight:500">${esc(f.nom)}</div>
        <div style="font-size:10px;color:var(--text-3)">${esc(f.ufr_nom || "-")} · Niveaux : ${esc(f.niveaux || "aucun")}</div>
      </div>
      <button class="btn btn_danger btn_sm" onclick="supprimerFiliere(${f.id},'${esc(f.nom)}')">
        <i class="fa-solid fa-trash"></i>
      </button>
    </div>
  `).join("");
}

async function onStructUfrChange(sel) {
  const ufr_id = sel.value;
  if (!ufr_id) return;
  const data     = await api(`api/admin.php?section=filieres&ufr_id=${ufr_id}`);
  const fil_list = document.getElementById("fil_list");
  if (fil_list) fil_list.innerHTML = renderFiliereList(data.filieres || []);
}

async function creerUfr() {
  const nom  = document.getElementById("ufr_nom_input")?.value.trim();
  const code = document.getElementById("ufr_code_input")?.value.trim();
  const desc = document.getElementById("ufr_desc_input")?.value.trim();
  if (!nom || !code) { showAlert("ufr_alert", "Nom et code sont obligatoires."); return; }
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "create_ufr", nom, code, description: desc })
  });
  if (data.success) {
    toast("UFR créée ✓", "success");
    loadAdminStructure();
  } else {
    showAlert("ufr_alert", data.message || "Erreur.");
  }
}

async function creerFiliere() {
  const ufr_id  = document.getElementById("fil_ufr_select")?.value;
  const nom     = document.getElementById("fil_nom_input")?.value.trim();
  const niveaux = [...document.querySelectorAll(".niveau_check:checked")].map(c => c.value);
  if (!ufr_id || !nom) { showAlert("fil_alert", "UFR et nom sont obligatoires."); return; }
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "create_filiere", ufr_id, nom, niveaux })
  });
  if (data.success) {
    toast("Filière créée ✓", "success");
    loadAdminStructure();
  } else {
    showAlert("fil_alert", data.message || "Erreur.");
  }
}

function supprimerUfr(id, nom) {
  openConfirmModal({
    title:         "Supprimer cette UFR",
    message:       `<div class="alert alert_danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Toutes les filières de cette UFR seront supprimées. Les étudiants rattachés auront leur UFR remise à NULL.</div></div>`,
    target_name:   nom,
    confirm_label: "Supprimer",
    danger:        true,
    callback:      async () => {
      const data = await api("api/admin.php", { method: "POST", body: JSON.stringify({ action: "delete_ufr", id }) });
      if (data.success) { toast("UFR supprimée.", "info"); loadAdminStructure(); }
      else toast(data.message || "Erreur.", "error");
    }
  });
}

function supprimerFiliere(id, nom) {
  openConfirmModal({
    title:         "Supprimer cette filière",
    message:       `<div class="alert alert_danger"><i class="fa-solid fa-triangle-exclamation"></i><div>Les étudiants rattachés à cette filière auront leur filière_id remise à NULL.</div></div>`,
    target_name:   nom,
    confirm_label: "Supprimer",
    danger:        true,
    callback:      async () => {
      const data = await api("api/admin.php", { method: "POST", body: JSON.stringify({ action: "delete_filiere", id }) });
      if (data.success) { toast("Filière supprimée.", "info"); loadAdminStructure(); }
      else toast(data.message || "Erreur.", "error");
    }
  });
}

// étudiants admin 
async function loadAdminEtudiants(page = 1) {
  etudiants_page = page;
  const content  = document.getElementById("admin_content");
  const filters  = document.getElementById("admin_filter_bar");
  if (!content) return;

  const ufr_data = await api("api/admin.php?section=ufr");
  const ufr_opts = (ufr_data.ufr || []).map(u =>
    `<option value="${u.id}">${esc(u.nom)}</option>`
  ).join("");

  if (filters) {
    filters.innerHTML = `
      <div class="filter_search">
        <input class="input" style="width:200px;padding:5px 10px;font-size:11px"
               placeholder="🔍 Rechercher..." id="etud_search_input"
               oninput="rechercherEtudiants(this.value)">
      </div>
      <button class="btn btn_outline btn_sm" onclick="loadAdminEtudiants()">↺</button>
    `;
  }

  content.innerHTML = `
    <div class="grid_2" style="gap:16px;margin-bottom:18px">
      <!-- CSV import -->
      <div class="card">
        <div class="card_header"><div class="card_title"><i class="fa-solid fa-file-csv"></i> Import CSV</div></div>
        <div class="card_body">
          <div class="alert alert_info" style="margin-bottom:10px">
            <i class="fa-solid fa-circle-info"></i>
            <div><strong>Format :</strong> <code>carte,nom,prenom,ufr_id,filiere_id,niveau</code></div>
          </div>
          <div id="import_alert"></div>
          <div class="file_zone" id="csv_drop_zone"
               onclick="document.getElementById('csv_file_input').click()"
               ondragover="event.preventDefault();this.style.borderColor='var(--blue)'"
               ondragleave="this.style.borderColor=''"
               ondrop="event.preventDefault();handleCsvDrop(event)">
            <i class="fa-solid fa-file-csv"></i>
            <p id="csv_label">Cliquer ou glisser-déposer votre fichier CSV</p>
            <small>Format carte,nom,prenom,ufr_id,filiere_id,niveau · Max 5000 étudiants</small>
            <input type="file" id="csv_file_input" accept=".csv,text/csv" onchange="parseCsvFile(this)">
          </div>
          <div id="csv_preview" style="display:none;margin-top:10px"></div>
        </div>
      </div>

      <!-- Ajout manuel -->
      <div class="card">
        <div class="card_header"><div class="card_title"><i class="fa-solid fa-user-plus"></i> Ajouter manuellement</div></div>
        <div class="card_body">
          <div id="manual_etud_alert"></div>
          <div class="form_group">
            <label class="form_label">N° Carte <span class="form_required">*</span></label>
            <input type="text" id="man_carte" class="input" placeholder="SN-001234">
          </div>
          <div class="form_row">
            <div class="form_group">
              <label class="form_label">Nom <span class="form_required">*</span></label>
              <input type="text" id="man_nom" class="input" placeholder="DIALLO">
            </div>
            <div class="form_group">
              <label class="form_label">Prénom <span class="form_required">*</span></label>
              <input type="text" id="man_prenom" class="input" placeholder="Amadou">
            </div>
          </div>
          <div class="form_group">
            <label class="form_label">UFR</label>
            <select id="man_ufr" class="select" onchange="loadManFilieres()">
              <option value="">-- UFR --</option>${ufr_opts}
            </select>
          </div>
          <div class="form_row">
            <div class="form_group">
              <label class="form_label">Filière</label>
              <select id="man_filiere" class="select" disabled onchange="loadManNiveaux()">
                <option value="">-- Filière --</option>
              </select>
            </div>
            <div class="form_group">
              <label class="form_label">Niveau</label>
              <select id="man_niveau" class="select" disabled>
                <option value="">-- Niveau --</option>
              </select>
            </div>
          </div>
          <button class="btn btn_primary btn_full" onclick="ajouterEtudiantManuel()">
            <i class="fa-solid fa-plus"></i> Ajouter
          </button>
        </div>
      </div>
    </div>

    <!-- Liste étudiants -->
    <div class="card">
      <div class="card_header">
        <div class="card_title">
          <i class="fa-solid fa-list"></i>
          Étudiants autorisés
          <span class="badge badge_blue" id="etud_count">…</span>
        </div>
        <button class="btn btn_ghost btn_sm" onclick="loadAdminEtudiants()">↺</button>
      </div>
      <div id="etudiants_table_wrap">
        ${skeletonList(5)}
      </div>
    </div>
  `;

  await chargerTableEtudiants(page);
}

async function chargerTableEtudiants(page = 1, q = "") {
  etudiants_page = page;
  const wrap     = document.getElementById("etudiants_table_wrap");
  const count_el = document.getElementById("etud_count");
  if (!wrap) return;

  const params = `page=${page}&per_page=${PER_PAGE}${q ? `&q=${encodeURIComponent(q)}` : ""}`;
  const data   = await api(`api/admin.php?section=etudiants&${params}`);
  const etudiants = data.etudiants || [];
  const total     = data.total     || etudiants.length;

  if (count_el) count_el.textContent = total;

  if (!etudiants.length) {
    wrap.innerHTML = emptyState("fa-graduation-cap", "Aucun étudiant", "Importez la liste via CSV.");
    return;
  }

  wrap.innerHTML = `
    <div class="table_wrap" style="border:none;border-radius:0">
      <table>
        <thead>
          <tr>
            <th>Carte</th>
            <th>Nom & Prénom</th>
            <th>UFR</th>
            <th>Filière</th>
            <th>Niveau</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="etudiants_tbody">
          ${etudiants.map(u => `
            <tr>
              <td style="font-family:monospace;font-size:10px">${esc(u.carte_identite)}</td>
              <td style="font-weight:500">${esc(u.prenom)} ${esc(u.nom)}</td>
              <td style="font-size:11px;color:var(--text-2)" class="truncate" style="max-width:100px">${esc(u.ufr_nom || "-")}</td>
              <td style="font-size:11px;color:var(--text-2)">${esc(u.filiere_nom || "-")}</td>
              <td style="font-size:11px">${esc(u.niveau || "-")}</td>
              <td><span class="tag ${u.is_active ? "tag_ok" : "tag_ko"}">${u.is_active ? "Actif" : "Inactif"}</span></td>
              <td>
                <div style="display:flex;gap:4px">
                  <button class="btn ${u.is_active ? "btn_danger" : "btn_success"} btn_sm"
                          onclick="toggleEtudiant(${u.id},this)">
                    ${u.is_active ? "Désactiver" : "Activer"}
                  </button>
                  <button class="btn btn_outline btn_sm" onclick="resetPasswordEtudiant(${u.id},'${esc(u.prenom+' '+u.nom)}')">
                    <i class="fa-solid fa-key"></i>
                  </button>
                </div>
              </td>
            </tr>
          `).join("")}
        </tbody>
      </table>
      ${renderPager(total, page, PER_PAGE, "chargerTableEtudiants")}
    </div>
  `;
}

function rechercherEtudiants(q) {
  clearTimeout(window._etud_search_timer);
  window._etud_search_timer = setTimeout(() => chargerTableEtudiants(1, q), 300);
}

async function toggleEtudiant(id, btn) {
  if (btn) btn.disabled = true;
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "toggle_etudiant", id })
  });
  if (data.success) { toast(data.message || "Compte modifié.", "success"); loadAdminEtudiants(etudiants_page); }
  else { toast(data.message || "Erreur.", "error"); if (btn) btn.disabled = false; }
}

async function resetPasswordEtudiant(id, nom) {
  openConfirmModal({
    title:         `Réinitialiser le mot de passe`,
    message:       `<p style="font-size:13px">Un nouveau mot de passe temporaire sera généré pour <strong>${esc(nom)}</strong>. Il sera affiché une seule fois.</p>`,
    confirm_label: "Générer",
    danger:        false,
    callback:      async () => {
      const data = await api("api/admin.php", {
        method: "POST",
        body: JSON.stringify({ action: "reset_password", id })
      });
      if (data.success && data.temp_password) {
        document.getElementById("adminModalTitle").textContent = "Nouveau mot de passe";
        document.getElementById("adminModalBody").innerHTML = `
          <div class="alert alert_warning" style="margin-bottom:12px">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Communiquez ce mot de passe à l'étudiant de façon sécurisée. Il ne sera plus affiché.</span>
          </div>
          <div style="text-align:center;padding:16px;background:var(--bg-2);border-radius:8px;border:1px solid var(--border)">
            <div style="font-size:1.5rem;font-weight:800;font-family:monospace;letter-spacing:2px;color:var(--navy)">${esc(data.temp_password)}</div>
          </div>
        `;
        document.getElementById("adminModalFooter").innerHTML = `
          <button class="btn btn_primary" onclick="navigator.clipboard.writeText('${esc(data.temp_password)}');toast('Copié !','success')">
            <i class="fa-solid fa-copy"></i> Copier
          </button>
          <button class="btn btn_outline" onclick="closeModal('adminModal')">Fermer</button>
        `;
        openModal("adminModal");
      } else {
        toast(data.message || "Erreur.", "error");
      }
    }
  });
}

// CSV import functions
let csv_parsed_data = [];

function parseCsvFile(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById("csv_label").textContent = file.name;
  const reader = new FileReader();
  reader.onload = (e) => {
    const lines   = e.target.result.split("\n").filter(l => l.trim());
    if (!lines.length) { toast("Fichier CSV vide.", "error"); return; }
    const first   = lines[0].toLowerCase();
    const start   = first.includes("carte") || first.includes("nom") ? 1 : 0;
    csv_parsed_data = [];
    lines.slice(start).forEach((line) => {
      const cols = line.split(",").map(c => c.trim().replace(/^["']|["']$/g, ""));
      if (cols.length < 3) return;
      csv_parsed_data.push({
        carte:      cols[0].toUpperCase(),
        nom:        cols[1].toUpperCase(),
        prenom:     cols[2],
        ufr_id:     cols[3] || null,
        filiere_id: cols[4] || null,
        niveau:     cols[5] || null
      });
    });
    showCsvPreview();
  };
  reader.readAsText(file, "UTF-8");
}

function handleCsvDrop(event) {
  const file = event.dataTransfer.files[0];
  if (!file) return;
  const dt   = new DataTransfer();
  dt.items.add(file);
  document.getElementById("csv_file_input").files = dt.files;
  parseCsvFile(document.getElementById("csv_file_input"));
}

function showCsvPreview() {
  const preview = document.getElementById("csv_preview");
  if (!preview) return;
  preview.style.display = "block";
  preview.innerHTML = `
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <span style="font-size:12px;font-weight:600;color:var(--green)">
        <i class="fa-solid fa-check"></i> ${csv_parsed_data.length} étudiant(s) détecté(s)
      </span>
      <div style="display:flex;gap:6px">
        <button class="btn btn_outline btn_sm" onclick="annulerCsvImport()">Annuler</button>
        <button class="btn btn_primary btn_sm" id="confirm_import_btn" onclick="confirmerCsvImport()">
          <i class="fa-solid fa-upload"></i> Importer
        </button>
      </div>
    </div>
    <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:6px">
      <table>
        <thead><tr><th>Carte</th><th>Nom</th><th>Prénom</th><th>UFR</th><th>Filière</th><th>Niveau</th></tr></thead>
        <tbody>
          ${csv_parsed_data.slice(0, 15).map(r => `
            <tr>
              <td style="font-size:10px;font-family:monospace">${esc(r.carte)}</td>
              <td style="font-size:10px">${esc(r.nom)}</td>
              <td style="font-size:10px">${esc(r.prenom)}</td>
              <td style="font-size:10px">${esc(r.ufr_id || "-")}</td>
              <td style="font-size:10px">${esc(r.filiere_id || "-")}</td>
              <td style="font-size:10px">${esc(r.niveau || "-")}</td>
            </tr>
          `).join("")}
          ${csv_parsed_data.length > 15 ? `<tr><td colspan="6" style="text-align:center;font-style:italic;font-size:10px;color:var(--text-3)">… et ${csv_parsed_data.length - 15} autres</td></tr>` : ""}
        </tbody>
      </table>
    </div>
  `;
}

function annulerCsvImport() {
  csv_parsed_data = [];
  const preview   = document.getElementById("csv_preview");
  const input     = document.getElementById("csv_file_input");
  const label     = document.getElementById("csv_label");
  if (preview) preview.style.display = "none";
  if (input)   input.value           = "";
  if (label)   label.textContent     = "Cliquer ou glisser-déposer votre fichier CSV";
}

async function confirmerCsvImport() {
  if (!csv_parsed_data.length) return;
  const btn = document.getElementById("confirm_import_btn");
  if (btn)  { btn.disabled = true; btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Import...`; }

  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "import_etudiants", rows: csv_parsed_data })
  });

  if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fa-solid fa-upload"></i> Importer`; }

  if (data.success) {
    toast(`Import : +${data.added} ajoutés, ${data.updated} mis à jour, ${data.errors} erreurs`, "success", 6000);
    annulerCsvImport();
    chargerTableEtudiants();
  } else {
    showAlert("import_alert", data.message || "Erreur d'import.");
  }
}

async function loadManFilieres() {
  const ufr_id = document.getElementById("man_ufr")?.value;
  const sel    = document.getElementById("man_filiere");
  if (!ufr_id || !sel) return;
  sel.innerHTML  = `<option value="">-- Filière --</option>`;
  sel.disabled   = true;
  const data     = await api(`api/auth.php?action=filieres&ufr_id=${ufr_id}`);
  (data.filieres || []).forEach(f => {
    sel.innerHTML += `<option value="${f.id}">${esc(f.nom)}</option>`;
  });
  sel.disabled = false;
  const niv_sel  = document.getElementById("man_niveau");
  if (niv_sel) { niv_sel.innerHTML = `<option value="">-- Niveau --</option>`; niv_sel.disabled = true; }
}

async function loadManNiveaux() {
  const fil_id = document.getElementById("man_filiere")?.value;
  const sel    = document.getElementById("man_niveau");
  if (!fil_id || !sel) return;
  sel.innerHTML = `<option value="">-- Niveau --</option>`;
  const data    = await api(`api/auth.php?action=niveaux&filiere_id=${fil_id}`);
  (data.niveaux || []).forEach(n => { sel.innerHTML += `<option value="${n}">${n}</option>`; });
  sel.disabled  = false;
}

async function ajouterEtudiantManuel() {
  const carte     = document.getElementById("man_carte")?.value.trim().toUpperCase();
  const nom       = document.getElementById("man_nom")?.value.trim().toUpperCase();
  const prenom    = document.getElementById("man_prenom")?.value.trim();
  const ufr_id    = document.getElementById("man_ufr")?.value;
  const filiere_id = document.getElementById("man_filiere")?.value;
  const niveau    = document.getElementById("man_niveau")?.value;

  if (!carte || !nom || !prenom) { showAlert("manual_etud_alert", "Carte, nom et prénom sont obligatoires."); return; }

  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({
      action: "import_etudiants",
      rows: [{ carte, nom, prenom, ufr_id: ufr_id || null, filiere_id: filiere_id || null, niveau: niveau || null }]
    })
  });

  if (data.success) {
    toast("Étudiant ajouté ✓", "success");
    ["man_carte","man_nom","man_prenom"].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = "";
    });
    chargerTableEtudiants();
  } else {
    showAlert("manual_etud_alert", data.message || "Erreur.");
  }
}

// demandes profil 
async function loadAdminDemandes() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const data    = await api("api/admin.php?section=demandes_profil");
  const demandes = data.demandes || [];
  updateSidebarBadge("sb_badge_demandes", demandes.filter(d => d.statut === "en_attente").length);

  if (!demandes.length) {
    content.innerHTML = emptyState("fa-inbox", "Aucune demande", "Toutes les demandes de changement de profil ont été traitées.");
    return;
  }

  content.innerHTML = `
    <div class="table_wrap">
      <table>
        <thead>
          <tr>
            <th>Étudiant</th>
            <th>Demande</th>
            <th>Statut</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${demandes.map(d => `
            <tr>
              <td style="font-weight:500">${esc(d.prenom || "")} ${esc(d.nom || "")}<br><span style="font-size:10px;color:var(--text-3);font-family:monospace">${esc(d.carte_identite || "")}</span></td>
              <td style="font-size:11px;color:var(--text-2)">
                Filière : ${esc(d.filiere_nom || "-")}<br>
                Niveau : ${esc(d.niveau || "-")}
              </td>
              <td><span class="tag ${d.statut==="en_attente"?"tag_pend":d.statut==="approuvee"?"tag_ok":"tag_ko"}">${esc(d.statut.replace("_"," "))}</span></td>
              <td style="font-size:10px;color:var(--text-3)">${formatDate(d.created_at)}</td>
              <td>
                ${d.statut === "en_attente" ? `
                  <div style="display:flex;gap:4px">
                    <button class="btn btn_success btn_sm" onclick="traiterDemande(${d.id},'approuver')">Approuver</button>
                    <button class="btn btn_danger btn_sm" onclick="traiterDemandeRefus(${d.id})">Refuser</button>
                  </div>
                ` : `<span style="font-size:11px;color:var(--text-3)">Traité</span>`}
              </td>
            </tr>
          `).join("")}
        </tbody>
      </table>
    </div>
  `;
}

async function traiterDemande(id, decision) {
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "traiter_demande", id, decision })
  });
  if (data.success) { toast("Demande traitée.", "success"); loadAdminDemandes(); }
  else toast(data.message || "Erreur.", "error");
}

function traiterDemandeRefus(id) {
  openMotifModal("Motif de refus", "Expliquez pourquoi la demande est refusée...", async (motif) => {
    await traiterDemande_avec_motif(id, "refuser", motif);
  });
}

async function traiterDemande_avec_motif(id, decision, motif) {
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "traiter_demande", id, decision, motif })
  });
  if (data.success) { toast("Demande refusée.", "info"); loadAdminDemandes(); }
  else toast(data.message || "Erreur.", "error");
}

// logs 
async function loadAdminLogs(page = 1) {
  logs_page      = page;
  const content  = document.getElementById("admin_content");
  const filters  = document.getElementById("admin_filter_bar");
  if (!content) return;

  if (filters) {
    filters.innerHTML = `
      <span class="filter_label">Type :</span>
      ${["tous","login","vote","candidature","login_failed","register"].map(f => `
        <span class="chip ${logs_filtre === f ? "active" : ""}"
              onclick="setLogsFiltre('${f}',this)">${f}</span>
      `).join("")}
    `;
  }

  const params  = logs_filtre !== "tous" ? `&type=${logs_filtre}` : "";
  const data    = await api(`api/admin.php?section=logs&page=${page}&per_page=${PER_PAGE}${params}`);
  const logs    = data.logs || [];
  const total   = data.total || logs.length;

  if (!logs.length) {
    content.innerHTML = emptyState("fa-scroll", "Aucun log", "");
    return;
  }

  content.innerHTML = `
    <div class="table_wrap">
      <table>
        <thead>
          <tr><th>Type</th><th>Action</th><th>IP</th><th>Date</th></tr>
        </thead>
        <tbody>
          ${logs.map(l => `
            <tr>
              <td><span class="tag" style="background:${logColor(l.type)}22;color:${logColor(l.type)}">${esc(l.type)}</span></td>
              <td style="font-size:11px;max-width:300px" class="truncate">${esc(l.action)}</td>
              <td style="font-size:10px;font-family:monospace;color:var(--text-3)">${esc(l.ip || "-")}</td>
              <td style="font-size:10px;color:var(--text-3)">${formatDateTime(l.created_at)}</td>
            </tr>
          `).join("")}
        </tbody>
      </table>
      ${renderPager(total, page, PER_PAGE, "loadAdminLogs")}
    </div>
  `;
}

function setLogsFiltre(filtre, chip_el) {
  logs_filtre = filtre;
  document.querySelectorAll("#admin_filter_bar .chip").forEach(c => c.classList.remove("active"));
  chip_el.classList.add("active");
  loadAdminLogs(1);
}

// admins 
async function loadAdminAdmins() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const [data, ufr_data] = await Promise.all([
    api("api/admin.php?section=admins"),
    api("api/admin.php?section=ufr")
  ]);

  const admins   = data.admins || [];
  const ufr_opts = (ufr_data.ufr || []).map(u =>
    `<option value="${u.id}">${esc(u.nom)}</option>`
  ).join("");

  content.innerHTML = `
    <div class="grid_2" style="gap:16px;margin-bottom:18px">
      <div class="card">
        <div class="card_header"><div class="card_title"><i class="fa-solid fa-user-plus"></i> Créer un admin</div></div>
        <div class="card_body">
          <div id="admin_create_alert"></div>
          <div class="form_group">
            <label class="form_label">Nom d'utilisateur <span class="form_required">*</span></label>
            <input type="text" id="new_admin_username" class="input" placeholder="admin_ufr_st">
          </div>
          <div class="form_group">
            <label class="form_label">Mot de passe <span class="form_required">*</span></label>
            <input type="password" id="new_admin_password" class="input" placeholder="Min 8 caractères">
          </div>
          <div class="form_group">
            <label class="form_label">Rôle</label>
            <select id="new_admin_role" class="select" onchange="toggleUfrSelect(this.value)">
              <option value="admin">Admin UFR</option>
              <option value="superadmin">Super Admin</option>
            </select>
          </div>
          <div class="form_group" id="new_admin_ufr_wrap">
            <label class="form_label">UFR (périmètre)</label>
            <select id="new_admin_ufr" class="select">
              <option value="">-- UFR --</option>${ufr_opts}
            </select>
          </div>
          <button class="btn btn_primary btn_full" onclick="creerAdmin()">
            <i class="fa-solid fa-plus"></i> Créer
          </button>
        </div>
      </div>
      <div style="display:flex;align-items:center;justify-content:center;color:var(--text-3);font-size:13px;text-align:center;padding:20px">
        <div>
          <i class="fa-solid fa-shield-halved" style="font-size:2rem;margin-bottom:12px;display:block;color:var(--blue)"></i>
          Seul le Super Admin peut créer d'autres comptes administrateurs.
        </div>
      </div>
    </div>

    <div class="table_wrap">
      <table>
        <thead><tr><th>Identifiant</th><th>Rôle</th><th>Périmètre</th><th>Statut</th><th></th></tr></thead>
        <tbody>
          ${admins.map(a => `
            <tr>
              <td style="font-weight:500">${esc(a.username)}</td>
              <td><span class="tag ${a.role==="superadmin"?"tag_tour2":"tag_pend"}">${esc(a.role)}</span></td>
              <td style="font-size:11px;color:var(--text-2)">${esc(a.ufr_nom || a.perimetre || "-")}</td>
              <td><span class="tag ${a.is_active ? "tag_ok" : "tag_ko"}">${a.is_active ? "Actif" : "Inactif"}</span></td>
              <td>
                ${a.role !== "superadmin" ? `
                  <button class="btn ${a.is_active ? "btn_danger" : "btn_success"} btn_sm"
                          onclick="toggleAdmin(${a.id},this)">
                    ${a.is_active ? "Désactiver" : "Activer"}
                  </button>
                ` : `<span style="font-size:10px;color:var(--text-3)">Protégé</span>`}
              </td>
            </tr>
          `).join("")}
        </tbody>
      </table>
    </div>
  `;
}

function toggleUfrSelect(role) {
  const wrap = document.getElementById("new_admin_ufr_wrap");
  if (wrap) wrap.style.display = role === "superadmin" ? "none" : "block";
}

async function creerAdmin() {
  const username = document.getElementById("new_admin_username")?.value.trim();
  const password = document.getElementById("new_admin_password")?.value;
  const role     = document.getElementById("new_admin_role")?.value;
  const ufr_id   = document.getElementById("new_admin_ufr")?.value;

  if (!username || !password) { showAlert("admin_create_alert", "Identifiant et mot de passe obligatoires."); return; }
  if (password.length < 8)    { showAlert("admin_create_alert", "Mot de passe minimum 8 caractères."); return; }

  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "create_admin", username, password, role, ufr_id: ufr_id || null })
  });

  if (data.success) { toast("Admin créé ✓", "success"); loadAdminAdmins(); }
  else showAlert("admin_create_alert", data.message || "Erreur.");
}

async function toggleAdmin(id, btn) {
  if (btn) btn.disabled = true;
  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "toggle_admin", id })
  });
  if (data.success) { toast("Admin mis à jour.", "success"); loadAdminAdmins(); }
  else { toast(data.message || "Erreur.", "error"); if (btn) btn.disabled = false; }
}

// paramètres 
async function loadAdminParametres() {
  const content = document.getElementById("admin_content");
  if (!content) return;

  const data = await api("api/admin.php?section=settings");
  const s    = data.settings || {};

  content.innerHTML = `
    <div class="card" style="max-width:560px">
      <div class="card_header"><div class="card_title"><i class="fa-solid fa-gear"></i> Paramètres généraux</div></div>
      <div class="card_body">
        <div id="params_alert"></div>
        <div class="form_group">
          <label class="form_label">Nom de l'application</label>
          <input type="text" id="param_app_name" class="input" value="${esc(s.app_name || "VoteNow")}">
        </div>
        <div class="form_group">
          <label class="form_label">Nom de l'université</label>
          <input type="text" id="param_univ_nom" class="input" value="${esc(s.univ_nom || "")}">
        </div>
        <div class="form_group">
          <label class="form_label">Message d'accueil</label>
          <textarea id="param_accueil_msg" class="textarea" rows="3">${esc(s.message_accueil || "")}</textarea>
        </div>
        <div class="form_group">
          <label class="form_label">Email de contact</label>
          <input type="email" id="param_contact_email" class="input" value="${esc(s.contact_admin || "")}">
        </div>
        <button class="btn btn_primary" onclick="sauvegarderParametres()">
          <i class="fa-solid fa-floppy-disk"></i> Sauvegarder
        </button>
      </div>
    </div>

    <div class="card" style="max-width:560px;margin-top:16px">
      <div class="card_header"><div class="card_title"><i class="fa-solid fa-database"></i> Backup</div></div>
      <div class="card_body">
        <p style="font-size:13px;color:var(--text-2);margin-bottom:12px">
          Exporter une sauvegarde complète de la base de données. Le fichier SQL sera téléchargé.
        </p>
        <button class="btn btn_outline" onclick="exporterBackup()">
          <i class="fa-solid fa-download"></i> Exporter la base complète
        </button>
      </div>
    </div>
  `;
}

async function sauvegarderParametres() {
  const settings = {
    app_name:       document.getElementById("param_app_name")?.value.trim(),
    univ_nom:       document.getElementById("param_univ_nom")?.value.trim(),
    message_accueil: document.getElementById("param_accueil_msg")?.value.trim(),
    contact_admin:  document.getElementById("param_contact_email")?.value.trim()
  };

  const data = await api("api/admin.php", {
    method: "POST",
    body: JSON.stringify({ action: "save_settings", settings })
  });

  if (data.success) toast("Paramètres sauvegardés ✓", "success");
  else showAlert("params_alert", data.message || "Erreur.");
}

function exporterBackup() {
  window.open("api/admin.php?action=export_backup", "_blank");
}

// modal créer élection 
async function openModalCreerElection() {
  const ufr_data = await api("api/admin.php?section=ufr");
  const ufr_opts = (ufr_data.ufr || []).map(u =>
    `<option value="${u.id}">${esc(u.nom)}</option>`
  ).join("");

  document.getElementById("adminModalTitle").textContent = "Créer une élection";
  document.getElementById("adminModalBody").innerHTML = `
    <div id="create_el_alert"></div>
    <div class="form_group">
      <label class="form_label">Titre <span class="form_required">*</span></label>
      <input type="text" id="el_titre" class="input" placeholder="Ex: Délégués L3 Informatique 2024-2025">
    </div>
    <div class="form_group">
      <label class="form_label">Description</label>
      <textarea id="el_desc" class="textarea" rows="2" placeholder="Contexte de l'élection..."></textarea>
    </div>
    <div class="form_row">
      <div class="form_group">
        <label class="form_label">Périmètre <span class="form_required">*</span></label>
        <select id="el_scope" class="select" onchange="onScopeChange(this.value)">
          <option value="universite">Université entière</option>
          <option value="ufr">UFR</option>
          <option value="filiere">Filière</option>
          <option value="niveau">Niveau</option>
        </select>
      </div>
      <div class="form_group">
        <label class="form_label">Résultats</label>
        <select id="el_resultats" class="select">
          <option value="apres_cloture">Après clôture</option>
          <option value="temps_reel">Temps réel</option>
        </select>
      </div>
    </div>
    <div id="el_scope_options" style="display:none">
      <div class="form_group">
        <label class="form_label">UFR</label>
        <select id="el_ufr" class="select" onchange="onElUfrChange()">
          <option value="">-- UFR --</option>${ufr_opts}
        </select>
      </div>
      <div id="el_filiere_wrap" style="display:none">
        <div class="form_group">
          <label class="form_label">Filière</label>
          <select id="el_filiere" class="select" onchange="onElFiliereChange()">
            <option value="">-- Filière --</option>
          </select>
        </div>
      </div>
      <div id="el_niveau_wrap" style="display:none">
        <div class="form_group">
          <label class="form_label">Niveau</label>
          <select id="el_niveau" class="select">
            <option value="">-- Niveau --</option>
          </select>
        </div>
      </div>
    </div>
    <div class="form_row">
      <div class="form_group">
        <label class="form_label">Début candidatures</label>
        <input type="datetime-local" id="el_date_cand_debut" class="input">
      </div>
      <div class="form_group">
        <label class="form_label">Fin candidatures</label>
        <input type="datetime-local" id="el_date_cand_fin" class="input">
      </div>
    </div>
    <div class="form_row">
      <div class="form_group">
        <label class="form_label">Début vote</label>
        <input type="datetime-local" id="el_date_vote_debut" class="input">
      </div>
      <div class="form_group">
        <label class="form_label">Fin vote</label>
        <input type="datetime-local" id="el_date_vote_fin" class="input">
      </div>
    </div>
  `;

  document.getElementById("adminModalFooter").innerHTML = `
    <button class="btn btn_outline" onclick="closeModal('adminModal')">Annuler</button>
    <button class="btn btn_primary" onclick="soumettreElection()">
      <i class="fa-solid fa-plus"></i> Créer
    </button>
  `;

  openModal("adminModal");
}

function onScopeChange(scope) {
  const opts = document.getElementById("el_scope_options");
  const fil  = document.getElementById("el_filiere_wrap");
  const niv  = document.getElementById("el_niveau_wrap");
  if (!opts) return;
  opts.style.display = scope !== "universite" ? "block" : "none";
  if (fil) fil.style.display = ["filiere","niveau"].includes(scope) ? "block" : "none";
  if (niv) niv.style.display = scope === "niveau"   ? "block" : "none";
}

async function onElUfrChange() {
  const ufr_id = document.getElementById("el_ufr")?.value;
  const sel    = document.getElementById("el_filiere");
  if (!ufr_id || !sel) return;
  sel.innerHTML  = `<option value="">-- Filière --</option>`;
  const data     = await api(`api/auth.php?action=filieres&ufr_id=${ufr_id}`);
  (data.filieres || []).forEach(f => { sel.innerHTML += `<option value="${f.id}">${esc(f.nom)}</option>`; });
}

async function onElFiliereChange() {
  const fil_id = document.getElementById("el_filiere")?.value;
  const sel    = document.getElementById("el_niveau");
  if (!fil_id || !sel) return;
  sel.innerHTML  = `<option value="">-- Niveau --</option>`;
  const data     = await api(`api/auth.php?action=niveaux&filiere_id=${fil_id}`);
  (data.niveaux || []).forEach(n => { sel.innerHTML += `<option value="${n}">${n}</option>`; });
}

async function soumettreElection() {
  const titre    = document.getElementById("el_titre")?.value.trim();
  if (!titre)    { showAlert("create_el_alert", "Le titre est obligatoire."); return; }

  const scope    = document.getElementById("el_scope")?.value;
  const ufr_id   = document.getElementById("el_ufr")?.value || null;
  const fil_id   = document.getElementById("el_filiere")?.value || null;
  const niveau   = document.getElementById("el_niveau")?.value || null;
  const resultats = document.getElementById("el_resultats")?.value;
  const dcd      = document.getElementById("el_date_cand_debut")?.value || null;
  const dcf      = document.getElementById("el_date_cand_fin")?.value || null;
  const dvd      = document.getElementById("el_date_vote_debut")?.value || null;
  const dvf      = document.getElementById("el_date_vote_fin")?.value || null;

  const data = await api("api/elections.php", {
    method: "POST",
    body: JSON.stringify({
      titre, description: document.getElementById("el_desc")?.value.trim(),
      scope, ufr_id, filiere_id: fil_id, niveau, resultats_publics: resultats,
      date_candidatures_debut: dcd, date_candidatures_fin: dcf,
      date_vote_debut: dvd, date_vote_fin: dvf
    })
  });

  if (data.success) {
    toast("Élection créée ✓", "success");
    closeModal("adminModal");
    loadAdminElections();
  } else {
    showAlert("create_el_alert", data.message || "Erreur.");
  }
}

// exposer au scope global 
window.initAdminLayout      = initAdminLayout;
window.loadAdminTab         = loadAdminTab;
window.loadAdminDashboard   = loadAdminDashboard;
window.loadAdminElections   = loadAdminElections;
window.loadAdminCandidatures = loadAdminCandidatures;
window.loadAdminResultats   = loadAdminResultats;
window.loadAdminStructure   = loadAdminStructure;
window.loadAdminEtudiants   = loadAdminEtudiants;
window.loadAdminAdmins      = loadAdminAdmins;
window.loadAdminDemandes    = loadAdminDemandes;
window.loadAdminLogs        = loadAdminLogs;
window.loadAdminParametres  = loadAdminParametres;
window.gererElection        = gererElection;
window.supprimerElection    = supprimerElection;
window.validerCand          = validerCand;
window.refuserCand          = refuserCand;
window.lancerTour2          = lancerTour2;
window.decisionAdmin        = decisionAdmin;
window.soumettreDecision    = soumettreDecision;
window.changerPhase         = changerPhase;
window.chargerResultatsAdmin = chargerResultatsAdmin;
window.reinitialiserVotes   = reinitialiserVotes;
window.creerUfr             = creerUfr;
window.creerFiliere         = creerFiliere;
window.supprimerUfr         = supprimerUfr;
window.supprimerFiliere     = supprimerFiliere;
window.onStructUfrChange    = onStructUfrChange;
window.toggleEtudiant       = toggleEtudiant;
window.resetPasswordEtudiant = resetPasswordEtudiant;
window.parseCsvFile         = parseCsvFile;
window.handleCsvDrop        = handleCsvDrop;
window.annulerCsvImport     = annulerCsvImport;
window.confirmerCsvImport   = confirmerCsvImport;
window.loadManFilieres      = loadManFilieres;
window.loadManNiveaux       = loadManNiveaux;
window.ajouterEtudiantManuel = ajouterEtudiantManuel;
window.traiterDemande       = traiterDemande;
window.traiterDemandeRefus  = traiterDemandeRefus;
window.creerAdmin           = creerAdmin;
window.toggleAdmin          = toggleAdmin;
window.toggleUfrSelect      = toggleUfrSelect;
window.sauvegarderParametres = sauvegarderParametres;
window.exporterBackup       = exporterBackup;
window.openModalCreerElection = openModalCreerElection;
window.onScopeChange        = onScopeChange;
window.onElUfrChange        = onElUfrChange;
window.onElFiliereChange    = onElFiliereChange;
window.soumettreElection    = soumettreElection;
window.setElectionFiltre    = setElectionFiltre;
window.setCandFiltre        = setCandFiltre;
window.setLogsFiltre        = setLogsFiltre;
window.rechercherElections  = rechercherElections;
window.rechercherEtudiants  = rechercherEtudiants;
window.chargerTableEtudiants = chargerTableEtudiants;
window.chargerResultatsAdmin = chargerResultatsAdmin;
