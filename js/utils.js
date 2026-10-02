/**
 * VoteNow - Utilitaires JS
 * Fonctions communes : api(), toast(), skeleton(), esc(), etc.
 * Signature: 2-space indent, camelCase fonctions, snake_case vars locales, double quotes */

"use strict";

// constante csrf (injectée par PHP via index.php) 
// window.CSRF_TOKEN est défini dans index.php avant ce script
// window.APP_ENV est défini dans index.php

// wrapper api() avec timeout + retry + toast réseau 
const DEFAULT_TIMEOUT_MS = 10000;
const MAX_RETRIES        = 1;

async function api(url, opts = {}, retries = MAX_RETRIES) {
  const controller = new AbortController();
  const timeout_id = setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);

  const default_headers = {
    "Content-Type": "application/json",
    "X-CSRF-Token": window.CSRF_TOKEN || ""
  };

  if (opts.headers) {
    opts.headers = { ...default_headers, ...opts.headers };
  } else {
    opts.headers = default_headers;
  }

  opts.signal = controller.signal;

  try {
    const response = await fetch(url, opts);
    clearTimeout(timeout_id);

    if (!response.ok && response.status >= 500) {
      throw new Error(`Serveur erreur ${response.status}`);
    }

    const data = await response.json();
    return data;
  } catch (err) {
    clearTimeout(timeout_id);

    if (err.name === "AbortError") {
      if (retries > 0) {
        toast("Connexion lente, nouvelle tentative...", "warning");
        return api(url, opts, retries - 1);
      }
      toast("Délai d'attente dépassé. Vérifiez votre connexion.", "error");
      return { success: false, message: "Timeout réseau." };
    }

    if (retries > 0 && err.message.includes("Failed to fetch")) {
      return api(url, opts, retries - 1);
    }

    toast("Vérifiez votre connexion internet.", "error");
    return { success: false, message: "Erreur réseau." };
  }
}

// toast notifications 
const TOAST_ICONS = {
  success: "fa-circle-check",
  error:   "fa-circle-xmark",
  warning: "fa-triangle-exclamation",
  info:    "fa-circle-info"
};

function toast(message, type = "info", duration = 4000) {
  let container = document.getElementById("toast_container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast_container";
    container.className = "toast_container";
    document.body.appendChild(container);
  }

  const el = document.createElement("div");
  el.className = `toast ${type}`;
  el.innerHTML = `
    <i class="fa-solid ${TOAST_ICONS[type] || TOAST_ICONS.info} toast_icon"></i>
    <span class="toast_msg">${esc(message)}</span>
    <button class="toast_close" onclick="this.closest('.toast').remove()">
      <i class="fa-solid fa-xmark"></i>
    </button>
  `;

  container.appendChild(el);

  setTimeout(() => {
    el.style.opacity = "0";
    el.style.transform = "translateX(20px)";
    el.style.transition = "all 0.3s ease";
    setTimeout(() => el.remove(), 300);
  }, duration);
}

// échappement XSS 
function esc(str) {
  if (str == null) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

// afficher alerte inline 
function showAlert(container_id, message, type = "danger") {
  const el = document.getElementById(container_id);
  if (!el) return;
  const icons = {
    danger: "fa-circle-xmark",
    warning: "fa-triangle-exclamation",
    info: "fa-circle-info",
    success: "fa-circle-check"
  };
  el.innerHTML = `
    <div class="alert alert_${type}">
      <i class="fa-solid ${icons[type] || icons.info}"></i>
      <span>${esc(message)}</span>
    </div>
  `;
}

function clearAlert(container_id) {
  const el = document.getElementById(container_id);
  if (el) el.innerHTML = "";
}

// squelettes de chargement 
function skeletonCards(count = 3, height = "80px") {
  return Array(count).fill(0).map(() =>
    `<div class="skeleton" style="height:${height};border-radius:12px;"></div>`
  ).join("");
}

function skeletonRows(count = 4) {
  return Array(count).fill(0).map((_, i) => `
    <tr>
      ${Array(5).fill(0).map(() =>
        `<td><div class="skeleton" style="height:14px;border-radius:4px;width:${60 + Math.random() * 30}%"></div></td>`
      ).join("")}
    </tr>
  `).join("");
}

function skeletonList(count = 4) {
  return Array(count).fill(0).map(() => `
    <div style="padding:12px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;">
      <div class="skeleton" style="width:34px;height:34px;border-radius:8px;flex-shrink:0;"></div>
      <div style="flex:1;">
        <div class="skeleton" style="height:12px;width:60%;border-radius:4px;margin-bottom:5px;"></div>
        <div class="skeleton" style="height:10px;width:35%;border-radius:4px;"></div>
      </div>
    </div>
  `).join("");
}

// empty state 
function emptyState(icon, title, subtitle = "", cta = "") {
  return `
    <div class="empty_state">
      <i class="fa-solid ${icon}"></i>
      <h3>${esc(title)}</h3>
      ${subtitle ? `<p>${esc(subtitle)}</p>` : ""}
      ${cta || ""}
    </div>
  `;
}

// modal générique 
function openModal(modal_id) {
  const modal = document.getElementById(modal_id);
  if (!modal) return;
  modal.classList.add("show");
  document.body.style.overflow = "hidden";
  // fermer sur clic extérieur
  modal.onclick = (e) => {
    if (e.target === modal) closeModal(modal_id);
  };
  // fermer sur Echap
  document.addEventListener("keydown", function escape_handler(e) {
    if (e.key === "Escape") {
      closeModal(modal_id);
      document.removeEventListener("keydown", escape_handler);
    }
  });
}

function closeModal(modal_id) {
  const modal = document.getElementById(modal_id);
  if (!modal) return;
  modal.classList.remove("show");
  document.body.style.overflow = "";
}

// modal motif (remplace prompt()) 
function openMotifModal(title, placeholder, callback) {
  document.getElementById("motifModalTitle").textContent = title;
  const input = document.getElementById("motifModalInput");
  input.placeholder = placeholder || "Expliquez la décision...";
  input.value = "";

  document.getElementById("motifModalConfirm").onclick = () => {
    const motif = input.value.trim();
    if (!motif) {
      toast("Le motif est obligatoire.", "warning");
      return;
    }
    closeModal("motifModal");
    callback(motif);
  };

  openModal("motifModal");
  setTimeout(() => input.focus(), 100);
}

// modal confirmation destructive avec re-saisie 
function openConfirmModal(opts) {
  // opts: { title, message, target_name, confirm_label, danger, callback }
  const title_el   = document.getElementById("confirmModalTitle");
  const msg_el     = document.getElementById("confirmModalMessage");
  const name_el    = document.getElementById("confirmModalName");
  const input_el   = document.getElementById("confirmModalInput");
  const btn_el     = document.getElementById("confirmModalBtn");
  const wrap_el    = document.getElementById("confirmModalInputWrap");

  if (title_el) title_el.textContent = opts.title || "Confirmer";
  if (msg_el)   msg_el.innerHTML     = opts.message || "";

  if (opts.target_name && name_el && wrap_el && input_el) {
    name_el.textContent = opts.target_name;
    wrap_el.style.display = "block";
    input_el.value = "";
    input_el.placeholder = `Tapez "${opts.target_name}" pour confirmer`;
  } else if (wrap_el) {
    wrap_el.style.display = "none";
  }

  if (btn_el) {
    btn_el.textContent = opts.confirm_label || "Confirmer";
    btn_el.className = `btn ${opts.danger ? "btn_danger" : "btn_primary"}`;
    btn_el.onclick = () => {
      if (opts.target_name && input_el) {
        if (input_el.value.trim() !== opts.target_name) {
          toast(`Tapez exactement "${opts.target_name}" pour confirmer.`, "warning");
          return;
        }
      }
      closeModal("confirmModal");
      opts.callback();
    };
  }

  openModal("confirmModal");
}

// lightbox 
function openLightbox(src, alt = "") {
  const lb = document.getElementById("lightbox");
  const img = document.getElementById("lightboxImg");
  if (!lb || !img) return;
  img.src = src;
  img.alt = alt;
  lb.classList.add("show");
  document.body.style.overflow = "hidden";
  lb.onclick = (e) => { if (e.target === lb || e.target === img) closeLightbox(); };
  document.addEventListener("keydown", function lb_handler(e) {
    if (e.key === "Escape") { closeLightbox(); document.removeEventListener("keydown", lb_handler); }
  });
}

function closeLightbox() {
  const lb = document.getElementById("lightbox");
  if (lb) lb.classList.remove("show");
  document.body.style.overflow = "";
}

// pagination helper 
function renderPager(total, current_page, per_page, on_page_click) {
  const total_pages = Math.ceil(total / per_page);
  if (total_pages <= 1) return "";

  const pages = [];
  for (let i = 1; i <= total_pages; i++) {
    if (
      i === 1 || i === total_pages ||
      (i >= current_page - 1 && i <= current_page + 1)
    ) {
      pages.push(i);
    } else if (pages[pages.length - 1] !== "…") {
      pages.push("…");
    }
  }

  const btns = pages.map(p => {
    if (p === "…") return `<span class="pager_btn" style="cursor:default;color:var(--text-3)">…</span>`;
    return `<button class="pager_btn ${p === current_page ? "active" : ""}" onclick="${on_page_click}(${p})">${p}</button>`;
  }).join("");

  return `
    <div class="table_pager">
      <span class="pager_info">${total} entrée(s) · Page ${current_page} sur ${total_pages}</span>
      <div class="pager_btns">
        <button class="pager_btn" onclick="${on_page_click}(${current_page - 1})" ${current_page === 1 ? "disabled" : ""}>‹ Préc.</button>
        ${btns}
        <button class="pager_btn" onclick="${on_page_click}(${current_page + 1})" ${current_page === total_pages ? "disabled" : ""}>Suiv. ›</button>
      </div>
    </div>
  `;
}

// compte à rebours 
function renderCountdown(end_date_str) {
  const end = new Date(end_date_str);

  function update(el) {
    const now  = new Date();
    const diff = end - now;
    if (diff <= 0) { el.innerHTML = `<span style="color:var(--text-3);font-size:11px">Terminé</span>`; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.innerHTML = `
      <div class="countdown">
        <div class="ct_item"><div class="ct_val">${String(d).padStart(2, "0")}</div><div class="ct_lbl">j</div></div>
        <div class="ct_item"><div class="ct_val">${String(h).padStart(2, "0")}</div><div class="ct_lbl">h</div></div>
        <div class="ct_item"><div class="ct_val">${String(m).padStart(2, "0")}</div><div class="ct_lbl">m</div></div>
        <div class="ct_item"><div class="ct_val">${String(s).padStart(2, "0")}</div><div class="ct_lbl">s</div></div>
      </div>
    `;
  }

  const id = "ct_" + Math.random().toString(36).slice(2, 8);
  setTimeout(() => {
    const el = document.getElementById(id);
    if (!el) return;
    update(el);
    setInterval(() => update(el), 1000);
  }, 50);

  return `<div id="${id}"></div>`;
}

// theme toggle 
function initTheme() {
  const saved = localStorage.getItem("vn_theme") || "light";
  document.documentElement.setAttribute("data-theme", saved);
  updateThemeIcon(saved);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute("data-theme") || "light";
  const next    = current === "dark" ? "light" : "dark";
  document.documentElement.setAttribute("data-theme", next);
  localStorage.setItem("vn_theme", next);
  updateThemeIcon(next);
}

function updateThemeIcon(theme) {
  const btn = document.getElementById("themeBtn");
  if (!btn) return;
  btn.innerHTML = theme === "dark"
    ? `<i class="fa-solid fa-sun"></i>`
    : `<i class="fa-solid fa-moon"></i>`;
}

// navigation pages (SPA) 
let current_page = "accueil";

function showPage(page_id) {
  document.querySelectorAll(".page").forEach(p => p.classList.remove("active"));
  document.querySelectorAll(".nav_link").forEach(l => l.classList.remove("active"));
  document.querySelectorAll(".bottom_tab").forEach(t => t.classList.remove("active"));

  const page_el = document.getElementById(`page_${page_id}`);
  if (page_el) page_el.classList.add("active");

  document.querySelectorAll(`[data-page="${page_id}"]`).forEach(el => el.classList.add("active"));

  current_page = page_id;
  window.history.replaceState({}, "", `?p=${page_id}`);

  // fermer menu mobile si ouvert
  const mob_nav = document.getElementById("mobileNav");
  if (mob_nav) mob_nav.classList.remove("open");
}

function toggleMobileMenu() {
  const nav = document.getElementById("mobileNav");
  if (nav) nav.classList.toggle("open");
}

// format date 
function formatDate(date_str) {
  if (!date_str) return "-";
  const d = new Date(date_str);
  return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric" });
}

function formatDateTime(date_str) {
  if (!date_str) return "-";
  const d = new Date(date_str);
  return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

function timeAgo(date_str) {
  if (!date_str) return "";
  const diff = Date.now() - new Date(date_str);
  const mins  = Math.floor(diff / 60000);
  const hours = Math.floor(diff / 3600000);
  const days  = Math.floor(diff / 86400000);
  if (mins < 1)   return "à l'instant";
  if (mins < 60)  return `il y a ${mins} min`;
  if (hours < 24) return `il y a ${hours}h`;
  return `il y a ${days}j`;
}

// tag phase 
function phaseTag(phase, tour = 1) {
  const phases = {
    brouillon:            { label: "Brouillon",           cls: "tag_brou",  icon: "fa-pencil" },
    candidatures:         { label: "Candidatures",        cls: "tag_cand",  icon: "fa-file-signature" },
    candidatures_fermees: { label: "Cand. fermées",       cls: "tag_arch",  icon: "fa-lock" },
    vote:                 { label: tour > 1 ? "Vote T2" : "Vote ouvert", cls: tour > 1 ? "tag_tour2" : "tag_vote", icon: "fa-vote-yea" },
    vote_ferme:           { label: "Vote clos",           cls: "tag_arch",  icon: "fa-flag-checkered" },
    archivee:             { label: "Archivée",            cls: "tag_arch",  icon: "fa-archive" }
  };
  const p = phases[phase] || { label: phase, cls: "tag_arch", icon: "fa-question" };
  const dot = phase === "vote" ? `<span class="dot_live"></span>` : "";
  return `<span class="tag ${p.cls}">${dot}<i class="fa-solid ${p.icon}" style="font-size:9px"></i> ${esc(p.label)}</span>`;
}

// scope label 
function scopeLabel(election) {
  switch (election.scope) {
    case "universite": return "🏛️ Université entière";
    case "ufr":        return `🏫 UFR · ${esc(election.ufr_nom || "-")}`;
    case "filiere":    return `📚 Filière · ${esc(election.filiere_nom || "-")}`;
    case "niveau":     return `🎓 ${esc(election.filiere_nom || "-")} · ${esc(election.niveau || "-")}`;
    default:           return election.scope || "-";
  }
}

// barre participation mini 
function partBar(votants, eligibles, color_class = "blue") {
  const pct = eligibles > 0 ? Math.round((votants / eligibles) * 100) : 0;
  return `
    <div class="prog_mini" style="display:flex;align-items:center;gap:6px">
      <div class="part_track" style="width:60px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
        <div class="part_fill ${color_class}" style="width:${pct}%;height:100%;border-radius:2px;background:var(--blue)"></div>
      </div>
      <span style="font-size:9px;color:var(--text-3)">${votants}/${eligibles > 0 ? eligibles : "?"}</span>
    </div>
  `;
}

// exposer au scope global (appelé depuis HTML onclick) 
window.api            = api;
window.toast          = toast;
window.esc            = esc;
window.showAlert      = showAlert;
window.clearAlert     = clearAlert;
window.openModal      = openModal;
window.closeModal     = closeModal;
window.openMotifModal = openMotifModal;
window.openConfirmModal = openConfirmModal;
window.openLightbox   = openLightbox;
window.closeLightbox  = closeLightbox;
window.showPage       = showPage;
window.toggleMobileMenu = toggleMobileMenu;
window.toggleTheme    = toggleTheme;
window.phaseTag       = phaseTag;
window.scopeLabel     = scopeLabel;
window.formatDate     = formatDate;
window.formatDateTime = formatDateTime;
window.timeAgo        = timeAgo;
window.renderPager    = renderPager;
window.renderCountdown = renderCountdown;
window.skeletonCards  = skeletonCards;
window.skeletonRows   = skeletonRows;
window.skeletonList   = skeletonList;
window.emptyState     = emptyState;
window.partBar        = partBar;
