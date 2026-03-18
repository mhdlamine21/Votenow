# 🛡️ Guide & Politique de Sécurité - VoteNow

> **Projet Académique :** Sécurité d'une application web  
> **Module :** Introduction à la Sécurité (Semestre 4, Licence 2 - Année Universitaire 2025-2026)  
> **Établissement :** Université Iba Der Thiam de Thiès (UIDT)

---

## 👥 Auteurs & Équipe du Projet

Ce projet de sécurité web a été réalisé en groupe de trois étudiants dans le cadre du cours d'**Introduction à la Sécurité** :

* **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** - Étudiant en Licence 2 Informatique *(Auteur initial du projet VoteNow V1)*
* **[Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn)** - Étudiant en Licence 2 Informatique
* **[Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)** - Étudiant en Licence 2 Informatique

---

## 📖 Contexte et Démarche Académique

### 1. La Genèse : Du Projet Web 1 au Laboratoire de Sécurité
* **Semestre 3 (Décembre 2024) - Examen de Dév Web 1** :  
  L'application **VoteNow V1** est née dans le cadre de l'examen individuel pratique assigné à toute la promotion de Licence 2. Conçue et développée individuellement par **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** exclusivement en **HTML, CSS moderne et JavaScript Vanilla (avec LocalStorage)**, elle offrait une interface soignée pour le vote étudiant, mais sans backend persistant ni garanties de sécurité serveur.
* **Semestre 4 (Mars - Avril 2026) - Introduction à la Sécurité** :  
  Dans le cadre de l'évaluation semestrielle, notre enseignant nous a confié un travail pratique par groupe de 3 ([Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com), [Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn), [Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)) sur le thème : **« Sécurité d'une application web »**.  
  L'enseignant n'ayant pas imposé d'application, notre équipe a choisi de retenir la version VoteNow de Mouhamadou Lamine NIANG pour lui développer un backend complet en **PHP & MySQL** et en faire notre laboratoire réel d'audit pour :
  1. Identifier les failles courantes du Web (Top 10 OWASP) grâce à l'outil **OWASP ZAP**.
  2. Démontrer concrètement les attaques et évaluer leur impact opérationnel.
  3. Implémenter et documenter les contre-mesures techniques adaptées.
* **Modernisation récente (Août 2026)** :  
  Conteneurisation via **Docker & Docker Compose** pour assurer la reproductibilité immédiate de l'environnement de test, des bases de données et des outils d'audit.

---

## 🔍 Outils d'Audit et Méthodologie : OWASP ZAP

Dans notre démarche d'audit, nous avons utilisé l'outil **OWASP ZAP (Zed Attack Proxy)**, scanner de vulnérabilités open-source de référence recommandé par l'OWASP.

### Ce que nous avons appris avec OWASP ZAP :
1. **Spidering / Crawling automatique** : Découverte exhaustive des points d'entrée de l'application (formulaires de vote, endpoints d'authentification API, paramètres GET/POST).
2. **Scan Passif** : Analyse des en-têtes de sécurité HTTP sans altérer le trafic (détection de l'absence des drapeaux `HttpOnly`/`SameSite` sur les cookies, manque de `X-Frame-Options`, absence d'en-têtes CSP).
3. **Scan Actif (Fuzzing)** : Injection automatisée de vecteurs d'attaque pour déceler les injections SQL, les failles XSS réfléchies et persistantes, et le manque de jetons CSRF.
4. **Hiérarchisation des risques** : Classification des alertes selon leur sévérité (*High, Medium, Low, Informational*) pour prioriser le plan de remédiation.

---

## 🎯 Démonstrations Concrètes des Vulnérabilités & Remédiations

Lors de notre présentation orale, nous avons sélectionné et démontré trois vulnérabilités critiques identifiées lors du scan OWASP ZAP :

```
       ┌────────────────────────────────────────────────────────┐
       │             Vecteurs d'Attaque Démontrés               │
       └────────────────────────────────────────────────────────┘
                   │                  │                  │
                   ▼                  ▼                  ▼
          ┌─────────────────┐ ┌───────────────┐ ┌─────────────────┐
          │  Injection SQL  │ │   Faille XSS  │ │   Faille CSRF   │
          │ (Auth Bypass)   │ │  (JavaScript) │ │  (Vote Forcé)   │
          └─────────────────┘ └───────────────┘ └─────────────────┘
```

---

### 🔴 1. Démonstration : Injection SQL (Bypass d'Authentification)

#### 🎯 Scénario & Action
L'attaque cible le formulaire de connexion (`api/auth.php`). L'attaquant saisit dans le champ d'identifiant :
```text
' OR 1=1#
```
*(ou `' OR 1=1 --` selon le moteur SQL)*, avec n'importe quel mot de passe.

#### ⚙️ Mécanisme Technique
La requête SQL vulnérable concatène directement les valeurs fournies par l'utilisateur :
```sql
SELECT * FROM users WHERE dossier = '' OR 1=1#' AND password = '...' LIMIT 1;
```
L'expression `' OR 1=1` rend la condition logique de la clause `WHERE` universellement vraie (`TRUE`). Le caractère `#` commente la suite de la requête (neutralisant la vérification du mot de passe). Le SGBD renvoie le premier enregistrement de la table, qui correspond généralement au compte **Super Administrateur**.

#### ⚠️ Impact
* Contournement total des mécanismes d'authentification sans connaître le mot de passe.
* Accès complet à l'espace d'administration et aux prérogatives élevées.
* Risque de fuite massive des données électorales et de manipulation directe des scrutins.

#### 🛡️ Code Vulnérable vs Code Sécurisé

* **Code Vulnérable (Concaténation directe) :**
  ```php
  // DANGEREUX : La donnée utilisateur modifie la structure de la requête
  $sql = "SELECT * FROM users WHERE dossier = '$dossier' AND password = '$password' LIMIT 1";
  $result = $conn->query($sql);
  ```

* **Code Sécurisé (Requêtes Préparées PDO / MySQLi) :**
  ```php
  // SÉCURISÉ : Séparation stricte entre code SQL et données utilisateur
  $stmt = $pdo->prepare("SELECT * FROM users WHERE dossier = :dossier LIMIT 1");
  $stmt->execute(['dossier' => $dossier]);
  $user = $stmt->fetch();

  // Vérification cryptographique avec Bcrypt (RFC 2898)
  if ($user && password_verify($password, $user['password_hash'])) {
      // Authentification réussie
  }
  ```

---

### 🔴 2. Démonstration : Cross-Site Scripting (XSS Stored / Reflected)

#### 🎯 Scénario & Action
L'attaquant injecte une charge utile JavaScript dans un champ textuel (titre de proposition, commentaire, avis ou retour de vote) :
```html
<script>alert('XSS - Faille Détectée !')</script>
```
ou via un vecteur d'événement furtif :
```html
<img src="x" onerror="alert('XSS Vulnérabilité VoteNow!')">
```

#### ⚙️ Mécanisme Technique
L'application stocke ou réaffiche la chaîne brute fournie par l'utilisateur sans neutraliser les caractères spéciaux HTML (`<`, `>`, `"`, `'`, `&`). Lorsque la page est consultée par une autre victime (ex: un administrateur consultant les candidatures), le navigateur interprète les balises injectées comme du code JavaScript légitime et l'exécute immédiatement.

#### ⚠️ Impact
* **Vol de session** : Accès à `document.cookie` permettant le détournement de compte (*Session Hijacking*).
* **Défaçage et Redirection** : Redirection des électeurs vers de fausses plateformes d'hameçonnage (*phishing*).
* **Actions non consenties** : Exécution de requêtes AJAX au nom et avec les privilèges de la victime connectée.

#### 🛡️ Code Vulnérable vs Code Sécurisé

* **Code Vulnérable (Affichage brut) :**
  ```php
  <!-- DANGEREUX : Le navigateur interprète les balises HTML/JS -->
  <div class="user-comment">
      <?php echo $comment['content']; ?>
  </div>
  ```

* **Code Sécurisé (Échappement contextuel strict) :**
  ```php
  <!-- SÉCURISÉ : Les caractères spéciaux sont convertis en entités HTML inoffensives -->
  <div class="user-comment">
      <?php echo htmlspecialchars($comment['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
  </div>
  ```
  *(Exemple : `<script>` devient `&lt;script&gt;`, affiché textuellement à l'écran sans jamais être exécuté).*

---

### 🔴 3. Démonstration : Cross-Site Request Forgery (CSRF - Vote Forcé)

#### 🎯 Scénario & Action
OWASP ZAP a relevé l'absence de jetons anti-CSRF sur les formulaires d'action.  
Un électeur connecté sur VoteNow visite en parallèle une page piégée hébergée sur un domaine malveillant (ex: `http://site-attaquant.com/bonus-etudiant.html`). Cette page contient un script masqué qui soumet automatiquement un formulaire vers l'API de vote :

```html
<!-- Page piège exécutée à l'insu de l'utilisateur -->
<form id="csrfForm" action="http://localhost:8000/api/votes.php" method="POST">
    <input type="hidden" name="action" value="voter">
    <input type="hidden" name="election_id" value="1">
    <input type="hidden" name="candidat_id" value="3"> <!-- Candidat de l'attaquant -->
</form>
<script>document.getElementById('csrfForm').submit();</script>
```

#### ⚙️ Mécanisme Technique
Comme la victime dispose d'une session active sur VoteNow, son navigateur envoie automatiquement le cookie de session `PHPSESSID` avec la requête POST inter-domaines. Le serveur VoteNow, ne vérifiant pas l'origine de l'intention de l'utilisateur, valide le vote.

#### ⚠️ Impact
* Falsification du scrutin électoral : un utilisateur vote sans le savoir pour un candidat qu'il n'a pas choisi.
* Impossibilité pour l'électeur de voter ensuite (règle du vote unique).
* Perte de confiance irrémédiable dans l'intégrité du système de vote.

#### 🛡️ Code Vulnérable vs Code Sécurisé

* **Code Vulnérable (Traitement aveugle du POST) :**
  ```php
  // DANGEREUX : Seule l'existence d'une session est vérifiée, pas la légitimité du formulaire
  if ($_POST['action'] === 'voter' && isset($_SESSION['user_id'])) {
      enregistrerVote($_SESSION['user_id'], $_POST['election_id'], $_POST['candidat_id']);
  }
  ```

* **Code Sécurisé (Jeton Synchronizer Token Pattern) :**
  ```php
  // 1. Génération d'un jeton cryptographique aléatoire à l'ouverture de session
  if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }

  // 2. Vérification systématique sur toute requête modifiante (POST/PUT/DELETE)
  $tokenRecu = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'], $tokenRecu)) {
      http_response_code(403);
      die(json_encode(['error' => 'Échec de validation CSRF : requête illégitime']));
  }
  ```

---

## 📊 Bilan Pédagogique : Ce Que Nous Avons Appris

Notre travail pratique sur ce module d'Introduction à la Sécurité nous a permis de tirer plusieurs enseignements capitaux pour notre formation en informatique :

1. **La sécurité doit être conçue dès le départ (*Security by Design*)** :  
   Il est infiniment plus coûteux et complexe de colmater des failles après le développement que d'adopter des patrons d'architecture sécurisés dès la première ligne de code.
2. **Ne jamais faire confiance aux entrées utilisateur (*Never Trust User Input*)** :  
   Toutes les données provenant de l'extérieur (champs de formulaire, paramètres d'URL, en-têtes HTTP, cookies) doivent être validées, typées et assainies côté serveur.
3. **Le principe de défense en profondeur (*Defense in Depth*)** :  
   Une mesure unique ne suffit jamais. La sécurité de VoteNow repose sur plusieurs couches coordonnées :
   * **Base de données** : Requêtes préparées PDO + contraintes d'unicité SQL.
   * **Sessions HTTP** : Drapeaux `HttpOnly`, `SameSite=Strict`, `Secure` et régénération d'identifiant (`session_regenerate_id()`) pour barrer le Session Hijacking et la fixation.
   * **Authentification** : Algorithme de hachage robuste **Bcrypt** avec sel automatique, protection contre les attaques temporelles (*timing attacks*).
   * **Contrôle d'accès** : Verrouillage temporaire de compte après 5 échecs consécutifs et limitation de débit (*Rate Limiting*) par adresse IP.
   * **Protection HTTP** : En-têtes `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` via `.htaccess`.
4. **L'importance des outils d'audit automatisés** :  
   L'utilisation d'**OWASP ZAP** nous a démontré la valeur des scanners de vulnérabilités pour automatiser les tests de non-régression et auditer de larges surfaces d'attaque.

---

## 🛡️ Matrice de Synthèse des Mesures de Sécurité

| Menace / Vulnérabilité | Niveau de Risque Initial | Solution Technique Implémentée | Statut |
|:---|:---:|:---|:---:|
| **Injection SQL** | 🔴 Critique | PDO natif avec requêtes préparées et typage strict | ✅ Corrigé |
| **Cross-Site Scripting (XSS)** | 🔴 Critique | Échappement HTML systématique (`htmlspecialchars`) & `HttpOnly` | ✅ Corrigé |
| **Cross-Site Request Forgery (CSRF)** | 🟠 Élevé | Tokens aléatoires anti-CSRF validés avec `hash_equals()` | ✅ Corrigé |
| **Attaque Brute Force (Login)** | 🟠 Élevé | Verrouillage de compte (15 min après 5 échecs) + Rate Limiting | ✅ Corrigé |
| **Fraude Électorale (Double Vote)** | 🔴 Critique | Transactions MySQL ACID + contraintes d'unicité `(election_id, user_id)` | ✅ Corrigé |
| **Vol / Fixation de Session** | 🟠 Élevé | `session_regenerate_id(true)`, `SameSite=Strict`, cookies `HttpOnly` | ✅ Corrigé |
| **Attaque par Clickjacking** | 🟡 Moyen | En-têtes HTTP `X-Frame-Options: DENY` | ✅ Corrigé |
| **Divulgation de Fichiers Sensibles** | 🟠 Élevé | Blocage des accès directs aux dossiers `/config` et `/logs` via `.htaccess` | ✅ Corrigé |
| **Soumission Automatique par Bots** | 🟡 Moyen | Champs pièges invisibles (*Honeypot*) sur tous les formulaires | ✅ Corrigé |

---

## 📬 Signalement Responsable de Vulnérabilités

Dans le cadre académique et pour la pérennité du projet, toute découverte de vulnérabilité additionnelle peut être signalée directement aux membres de l'équipe de développement :

* **Contact principal :** [Mouhamadou Lamine Niang](mailto:mouhamedlniang@gmail.com)  
* **Co-auteurs :** [Mamadou Sy](mailto:mamadou.sy7@univ-thies.sn) & [Papa Mangoné Gueye](mailto:pmangone.gueye@univ-thies.sn)  
* **Objet recommandé :** `[SECURITY AUDIT] VoteNow - Signalement`

*Nous nous engageons à analyser et tester tout retour dans un cadre strictement pédagogique et éthique.*
