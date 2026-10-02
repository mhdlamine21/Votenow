# Changelog - VoteNow

Toutes les modifications notables sont documentées ici.
Format : [Semantic Versioning](https://semver.org)

---

## [2.0.0] - Version Finale (Fullstack PHP/MySQL & Sécurité OWASP) - 2026-04-15

### Ajouté
- Migration complète vers une architecture Fullstack PHP modulaire avec base de données relationnelle MySQL (`database.sql`)
- Suite complète de sécurité web (conformité OWASP) :
  - Protection anti-CSRF par jetons aléatoires `openssl_random_pseudo_bytes` validés avec `hash_equals`
  - Protection systématique contre l'injection SQL via requêtes préparées PDO
  - Échappement contextuel XSS (`htmlspecialchars`)
  - Protection anti-doublon vote multi-session via transaction SQL `SELECT FOR UPDATE`
  - Rate limiting par IP pour prévenir les attaques par force brute
  - Traçabilité et audit logs immuables avec métadonnées JSON
- Module complet de vote électronique avec séparation stricte de l'émargement et de l'urne (secret du vote)
- Gestion multi-scrutins simultanés (BDE, délégués de filières, conseil d'UFR)
- Dépôt et validation des candidatures avec upload de professions de foi et photos
- Dépouillement automatisé et affichage dynamique des résultats en temps réel
- Prise en charge des scrutins à 2 tours et arbitrage administratif
- Interface d'administration et Super Admin avec gestion des droits
- Import CSV étudiants avec détection des doublons et export SQL backup
- Exportation des procès-verbaux d'élection et listes d'émargement
- Système de notifications in-app pour électeurs et candidats
- Squelettes shimmer sur tous les états de chargement
- Pagination serveur LIMIT/OFFSET 50 par page sur toutes les listes
- Filtres contextuels (élections, candidatures, logs)
- Dashboard admin avec métriques, alertes et fil d'activité

### Modifié
- JS refactorisé en modules dédiés : `utils.js`, `elections.js`, `admin.js`
- CSS refactorisé avec variables globales, responsive 320px → 1440px, mode sombre et mode clair

### Corrigé
- Vérification MIME upload côté serveur via `finfo()`
- Sécurisation stricte des cookies de session (`HttpOnly`, `SameSite=Strict`, `Secure`)
- Correction des redirections post-authentification et validation stricte des formulaires

---

## [1.0.0] - Version Initiale (Frontend HTML/CSS/JavaScript Vanilla) - 2025-12-22

### Ajouté
- Conception initiale 100% frontend réalisée pour l'examen de Développement Web 1 (Semestre 3)
- Structure HTML5 sémantique et mise en page responsive CSS3 moderne
- Logique applicative en JavaScript Vanilla ES6+
- Persistance locale dans le `localStorage` du navigateur (catalogues des scrutins, simulation de vote, compteurs)
- Interface de démonstration et thème sombre / clair

