<div align="center">

<img src="assets/images/logo.webp" alt="VoteNow Logo" height="85" />

# 🗳️ VoteNow - Plateforme de Vote & Laboratoire de Sécurité Web

**Projet Universitaire - Licence 2 Informatique (Année 2025 - 2026)**  
_De l'application Frontend au Banc d'Essai d'Audit de Sécurité Applicative (OWASP ZAP)_

[![PHP](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B%20%7C%20MariaDB-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![OWASP ZAP](https://img.shields.io/badge/Audit-OWASP%20ZAP-orange?style=flat-square&logo=owasp)](https://www.zaproxy.org/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

[Bilan Académique & Rapport OWASP (BILAN_SECURITE.md)](BILAN_SECURITE.md) · [Politique de Sécurité (SECURITY.md)](SECURITY.md) · [Documentation V1 (HTML/JS)](docs/README_V1.md)

</div>

---

## 👥 Auteurs & Équipe du Projet

- **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** - Conception initiale V1 (HTML/CSS/JS) & Co-développement Sécurité / Backend
- **[Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn)** - Co-développement Sécurité / Backend & Audit OWASP ZAP
- **[Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)** - Co-développement Sécurité / Backend & Scénarios d'Attaque

---

## 📚 Contexte Académique & Histoire du Projet

Ce dépôt retrace l'évolution complète d'un projet étudiant sur deux semestres consécutifs à l'**Université Iba Der Thiam de Thiès (UIDT)** :

### 1. VoteNow V1 - Décembre 2024 (Semestre 3, L2 - Examen de Développement Web 1)

- **Cadre :** Examen individuel pratique assigné à toute la promotion de Licence 2. Chaque étudiant devait concevoir sa propre application de vote.
- **Réalisation :** L'application **VoteNow V1** conçue et développée individuellement par [Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com), respectant la contrainte stricte d'utiliser **uniquement HTML, CSS et JavaScript Vanilla**, avec stockage local dans le `localStorage` du navigateur.
- 📄 *Voir la documentation complète de cette phase initiale dans [docs/README_V1.md](docs/README_V1.md).*

### 2. VoteNow V2 - Mars à Avril 2026 (Semestre 4, L2 - Cours : Introduction à la Sécurité)

- **Équipe de 3 étudiants :** [Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com), [Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn) et [Mamadou SY](mailto:mamadou.sy7@univ-thies.sn).
- **Thème assigné :** **« Sécurité d'une application web »**.
- **Démarche du groupe :** Le professeur n'ayant pas imposé d'application spécifique, notre groupe a choisi de retenir l'application VoteNow de Mouhamadou Lamine NIANG comme socle de travail. Nous l'avons fait évoluer vers une **version 2 finale avec un backend complet en PHP & MySQL** afin d'en faire notre laboratoire réel d'audit et d'expérimentation pour :
  1. Auditer l'application à l'aide d'**OWASP ZAP**.
  2. Démontrer concrètement les failles détectées (Injection SQL, XSS, CSRF).
  3. Implémenter et documenter les contre-mesures de durcissement (requêtes préparées, encodage contextuel, jetons anti-CSRF, Bcrypt, sessions strictes).

---

## 🎯 Démonstrations des Failles & Guide de Test (OWASP ZAP)

Lors de notre soutenance orale, nous avons illustré le rôle du scanner **OWASP ZAP** et prouvé la présence des vulnérabilités avant de les corriger :

### 🔴 1. Injection SQL (Bypass Authentification)

- **Objectif :** Prendre le contrôle de l'application sans connaître le mot de passe administrateur.
- **Point d'entrée :** Champ de connexion (`api/auth.php`).
- **Payload injecté :**
  ```text
  ' OR 1=1#
  ```
  _(ou `' OR 1=1 --`)_
- **Résultat obtenu :** Accès direct au tableau de bord administrateur car la condition SQL devient toujours vraie (`1=1`).
- **Correction appliquée :** Utilisation systématique de requêtes préparées PDO (`prepare()` et `execute()`).

### 🔴 2. Cross-Site Scripting (XSS Stored / Reflected)

- **Objectif :** Exécuter du code JavaScript malveillant dans la session d'autres utilisateurs.
- **Point d'entrée :** Champs textuels (commentaires, avis, fiches de proposition).
- **Payloads injectés :**
  ```html
  <script>
    alert("XSS - VoteNow Faille Détectée !");
  </script>
  ```
  ou
  ```html
  <img src="x" onerror="alert('XSS!')" />
  ```
- **Résultat obtenu :** Une boîte de dialogue JavaScript s'affiche dans le navigateur de la victime.
- **Correction appliquée :** Échappement HTML contextuel strict avec `htmlspecialchars($input, ENT_QUOTES, 'UTF-8')`.

### 🔴 3. Cross-Site Request Forgery (CSRF - Vote Forcé)

- **Objectif :** Forcer un électeur authentifié à voter pour un candidat spécifique à son insu.
- **Cause :** Absence de jetons anti-CSRF relevée par l'alerte OWASP ZAP.
- **Mécanisme :** Soumission automatique d'une requête POST forgée depuis une page externe profitant de la session active.
- **Correction appliquée :** Génération d'un jeton aléatoire sécurisé par session (`random_bytes(32)`) et validation stricte avec `hash_equals()`.

---

## 🚀 Démarrage Rapide (Serveur Local PHP & MySQL)

### 1. Configuration de l'environnement

1. Cloner le dépôt et copier la configuration d'exemple :
   ```bash
   cp .env.example .env
   ```
2. Éditer le fichier `.env` si nécessaire avec vos paramètres de base de données.

### 2. Base de Données

1. Assurez-vous que le service MySQL (ou MariaDB / XAMPP / WampServer) est démarré.  
2. Importer le schéma de base de données et les données de test :
   ```bash
   mysql -u root -p votenow_db < database.sql
   mysql -u root -p votenow_db < test_data.sql
   ```

### 3. Lancement du Serveur

Lancer le serveur de développement PHP :
```bash
php -S localhost:8000
```
Ouvrir [http://localhost:8000](http://localhost:8000) dans votre navigateur.

---

## 🔑 Identifiants des Comptes de Test

| Rôle                  | Identifiant / Carte | Mot de passe  | Description                                  |
| --------------------- | ------------------- | ------------- | -------------------------------------------- |
| **Super Admin**       | `superadmin`        | `password`    | Contrôle total, gestion des scrutins et logs |
| **Admin Faculté**     | `admin_st`          | `password123` | Gestion du périmètre Sciences & Technologies |
| **Électeur Étudiant** | `SN-2024-001`       | `test123`     | Compte étudiant habilité à voter             |
| **Électeur Étudiant** | `SN-2024-002`       | `test123`     | Compte étudiant habilité à voter             |

---

## 🛠️ Stack Technique Globale

- **Frontend :** HTML5 sémantique, CSS3 (Glassmorphism, Dark/Light mode, animations), JavaScript ES6+ Vanilla
- **Backend :** PHP 7.4+ / 8.x (Architecture modulaire REST API, PDO, Bcrypt, Sessions sécurisées)
- **Base de Données :** MySQL 5.7+ / 8.0 (Intégrité référentielle, transactions et contraintes d'unicité)
- **Audit de Sécurité :** OWASP ZAP (Zed Attack Proxy)
- **Environnement Serveur :** Apache / Serveur Web intégré PHP

---

## 📑 En Savoir Plus

- Pour consulter l'analyse approfondie des vulnérabilités, les explications d'attaque et le bilan pédagogique, référez-vous au fichier **[SECURITY.md](SECURITY.md)**.
- Pour découvrir la genèse du projet V1 en pur JavaScript / LocalStorage, consultez **[docs/README_V1.md](docs/README_V1.md)**.
