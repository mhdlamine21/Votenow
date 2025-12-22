# Changelog - VoteNow

Toutes les modifications notables sont documentées ici.
Format : [Semantic Versioning](https://semver.org)

---

## [4.0.0] - 2026-03-30

### Ajouté
- Suite complète de sécurité web (conformité OWASP)
- Protection anti-CSRF par jetons aléatoires `openssl_random_pseudo_bytes`
- Protection contre l'injection SQL via requêtes préparées PDO
- Échappement systématique XSS (`htmlspecialchars`)
- Protection anti-doublon vote multi-session via transaction SQL `SELECT FOR UPDATE`
- Rate limiting par IP pour prévenir les attaques par force brute
- Traçabilité et audit logs immuables avec métadonnées JSON
- Squelettes shimmer sur tous les états de chargement
- Pagination serveur LIMIT/OFFSET 50 par page sur toutes les listes
- Filtres contextuels (élections, candidatures, logs)
- Dashboard admin avec mini-graphiques CSS, alertes urgentes et fil d'activité
- Export SQL backup depuis le panel Super Admin
- Confirmation avec re-saisie du nom pour suppressions critiques
- Conteneurisation Docker + `docker-compose.yml` + `Dockerfile` + `scripts/setup.php`
- CI/CD GitHub Actions (lint PHP, validation SQL, build Docker, tests)
- Script automatisé `deploy.sh`

### Modifié
- JS refactorisé en 3 modules dédiés : `utils.js`, `elections.js`, `admin.js`
- CSS refactorisé avec variables globales, responsive 320px → 1440px

### Corrigé
- Vérification MIME upload côté serveur via `finfo()`
- Sécurisation stricte des cookies de session (HttpOnly, SameSite=Strict, Secure)
- Correction des redirections post-authentification et validation des formulaires

---

## [3.0.0] - 2026-02-27

### Ajouté
- Interface Super Admin complète avec gestion des droits
- Import CSV étudiants avec détection des doublons (upsert)
- Module de notifications in-app pour les électeurs et candidats
- Exportation PDF des procès-verbaux d'élection et CSV d'émargement
- Prise en charge des scrutins à 2 tours et arbitrage administratif
- Thème sombre (dark mode) et amélioration ergonomique mobile

---

## [2.0.0] - 2026-01-29

### Ajouté
- Gestion multi-scrutins simultanés (BDE, délégués de filières, conseil d'UFR)
- Dépôt et validation des candidatures avec téléversement de professions de foi (PDF) et photos
- Système de vote électronique à bulletin secret (séparation stricte de l'émargement et de l'urne)
- Dépouillement automatisé et affichage dynamique des résultats en temps réel

---

## [1.0.0] - 2025-12-22

### Ajouté
- Structure initiale du projet et intégration HTML5 / CSS3 / JavaScript
- Migration vers architecture backend PHP modulaire (header, footer, index)
- Modélisation relationnelle MySQL (`database.sql`)
- Authentification des électeurs et administrateurs
- Configuration académique (UFR, filières, niveaux)
- Première maquette fonctionnelle de vote et tableau de bord
