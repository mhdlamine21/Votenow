# 📘 Bilan Académique & Rapport d'Audit de Sécurité - VoteNow

> **Module :** Introduction à la Sécurité  
> **Niveau & Semestre :** Licence 2 - Semestre 4 (Année Universitaire 2025 - 2026)  
> **Établissement :** Université Iba Der Thiam de Thiès (UIDT)  
> **Thème du Projet :** Sécurité d'une application web  

---

## 👥 Membres de l'Équipe

Ce travail pratique et cette étude de sécurité ont été menés par notre groupe de trois étudiants :

* **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** - Étudiant en L2 Informatique *(Auteur initial du projet VoteNow V1)*
* **[Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn)** - Étudiant en L2 Informatique
* **[Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)** - Étudiant en L2 Informatique

---

## 🧭 1. Genèse & Contexte de l'Évolution du Projet

Le projet **VoteNow** s'inscrit dans un continuum pédagogique au sein de notre cursus :

1. **Semestre 3 (Décembre 2024) - Examen de Développement Web 1** :
   * Examen individuel pratique assigné à toute la promotion de Licence 2. Chaque étudiant devait concevoir sa propre application de vote électronique.
   * L'application **VoteNow V1** a été conçue et développée individuellement par **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** selon les contraintes du sujet : 100% frontend en **HTML5, CSS3 et JavaScript Vanilla**, avec stockage local des votes dans le **LocalStorage**.

2. **Semestre 4 (Mars - Avril 2026) - Cours d'Introduction à la Sécurité** :
   * Notre enseignant a assigné par groupe de 3 le thème : **« Sécurité d'une application web »**, sans imposer d'application spécifique.
   * Notre groupe ([Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com), [Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn) et [Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)) a alors choisi de retenir la version VoteNow de Mouhamadou Lamine NIANG issue du semestre 3 comme point de départ.
   * Nous l'avons migrée et enrichie avec un backend complet en **PHP & MySQL** afin d'y introduire une architecture client/serveur, de l'auditer avec des outils professionnels (notamment **OWASP ZAP**), de reproduire concrètement les failles majeures du Web et de concevoir les correctifs de durcissement.

3. **Modernisation récente (Août 2026)** :
   * Intégration de conteneurs **Docker & Docker Compose** pour automatiser le déploiement instantané du banc d'essai (PHP 8, MySQL 8, phpMyAdmin).

---

## 🛠️ 2. Méthodologie et Outils Utilisés : OWASP ZAP

Pour auditer notre application, nous avons utilisé l'outil open-source de référence **OWASP ZAP (Zed Attack Proxy)**.

### Comment fonctionne OWASP ZAP ?
OWASP ZAP agit comme un proxy d'interception et un scanner de vulnérabilités automatisé :
* **Le Crawler / Spider** : Il explore méthodiquement l'application en suivant tous les liens, formulaires et endpoints API (GET et POST) pour cartographier l'ensemble de la surface d'attaque.
* **Le Scan Passif** : Il inspecte le trafic HTTP sans modifier les requêtes (détection des en-têtes manquants tels que `Content-Security-Policy`, absence des drapeaux `HttpOnly` ou `SameSite` sur les cookies, divulgation d'informations serveur).
* **Le Scan Actif (Fuzzing)** : Il injecte des charges utiles (*payloads*) malveillantes dans chaque paramètre afin d'observer la réponse du serveur (injections SQL, scripts XSS, failles de manipulation de paramètres).

### Les Alertes Détectées lors de notre audit initial :
1. **Alerte Rouge (Haute)** : Injection SQL possible sur les paramètres d'authentification (`username`, `carte`).
2. **Alerte Rouge (Haute)** : Faille Cross-Site Scripting (XSS Stored) sur les zones de commentaires et d'avis.
3. **Alerte Orange (Moyenne)** : Absence totale de jetons de protection anti-CSRF (*Anti-CSRF Tokens Missing*).
4. **Alerte Orange (Moyenne)** : Manque de drapeaux stricts sur les cookies de session (`HttpOnly` et `SameSite`).

---

## 💥 3. Analyse Détaillée des Attaques, Démonstrations & Solutions

---

### 🔴 A. L'Injection SQL (SQLi - Authentication Bypass)

#### 1. Comment fonctionne l'attaque ?
Dans une application mal sécurisée, les valeurs saisies par l'utilisateur sont concaténées directement dans la chaîne SQL envoyée au serveur de base de données :
```sql
SELECT * FROM admins WHERE username = '$username' AND password = '$password' LIMIT 1;
```
Lorsque l'attaquant saisit dans le champ d'identifiant :
```text
' OR 1=1#
```
La requête exécutée par MySQL devient :
```sql
SELECT * FROM admins WHERE username = '' OR 1=1#' AND password = '...' LIMIT 1;
```
* Le guillemet simple `'` referme prématurément la chaîne attendue pour `username`.
* La clause `OR 1=1` est une tautologie : elle s'évalue toujours à **VRAI** (`TRUE`), quelle que soit la valeur de `username`.
* Le caractère dièse `#` (ou `-- `) commente et annule tout le reste de la requête (y compris le test du mot de passe).
* **Résultat** : La base de données retourne la première ligne trouvée dans la table `admins`, qui est le compte **Super Administrateur**. L'application connecte alors l'attaquant sans qu'aucun mot de passe valide n'ait été fourni.

#### 2. Démonstration Pratique
* **URL de test :** `http://localhost:8000/index.php` (formulaire de connexion).
* **Identifiant :** `' OR 1=1#`
* **Mot de passe :** n'importe quelle valeur (ex: `test`).
* **Résultat visualisé :** Connexion immédiate avec le profil **Super Administrateur**.

#### 3. Comment la régler ?
Il faut remplacer la concaténation de chaînes par des **requêtes préparées** avec l'extension **PDO** ou **MySQLi** :
```php
// Code Sécurisé avec PDO
$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    // Connexion accordée
}
```

#### 4. Que signifie concrètement cette solution ?
La préparation d'une requête sépare physiquement la **structure syntaxique** du code SQL de ses **données** :
1. Le moteur de base de données compile d'abord le gabarit de la requête (il sait à l'avance qu'il y a une clause WHERE avec un paramètre).
2. Lorsque les données utilisateur (`' OR 1=1#`) sont transmises, elles sont traitées exclusivement comme une valeur littérale brute, et jamais comme du code SQL exécutable. Les guillemets et mots-clés (`OR`, `#`) perdent tout pouvoir de commande.

---

### 🔴 B. Le Cross-Site Scripting (XSS Stored / Persistant)

#### 1. Comment fonctionne l'attaque ?
Une faille XSS se produit lorsqu'une application web reçoit une donnée provenant d'un utilisateur et la réaffiche dans le navigateur sans l'avoir préalablement nettoyée ou encodée.

Si un attaquant poste dans un champ de commentaire :
```html
<script>alert('XSS - Faille Détectée !')</script>
```
ou un vecteur furtif via une fausse image :
```html
<img src="invalide" onerror="alert('XSS VoteNow!')">
```
Le serveur stocke ce code dans la base de données. Plus tard, lorsqu'un autre utilisateur ou un administrateur consulte cette page :
* Le serveur renvoie la chaîne brute `<div><script>...</script></div>`.
* Le moteur de rendu du navigateur parse le document, rencontre la balise `<script>`, l'interprète comme du code JavaScript légitime et l'exécute dans le contexte de la session de la victime.

#### 2. Dangers & Impact réel
* **Vol de session** : Lecture du cookie d'authentification via `document.cookie` pour usurper le compte de la victime (*Session Hijacking*).
* **Redirection malveillante** : Forcer le navigateur à se rediriger vers un faux site de vote pour subtiliser les identifiants.
* **Exécution d'actions** : Réaliser des votes ou des validations administratives à l'insu de l'utilisateur connecté.

#### 3. Démonstration Pratique
* **URL de test :** `http://localhost:8000/commentaires.php`
* **Champ commentaire :** `<img src=x onerror="alert('XSS!')">`
* **Résultat visualisé :** Une boîte de dialogue JavaScript surgit à l'écran dès l'affichage du commentaire.

#### 4. Comment la régler ?
Il faut systématiquement appliquer un **échappement contextuel des entités HTML** sur toute donnée avant son affichage :
```php
<!-- Code Vulnérable -->
<div><?php echo $cm['content']; ?></div>

<!-- Code Sécurisé -->
<div><?php echo htmlspecialchars($cm['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
```

#### 5. Que signifie concrètement cette solution ?
La fonction `htmlspecialchars` transforme les caractères syntaxiques HTML en leurs équivalents inoffensifs d'affichage (*entités HTML*) :
* `<` devient `&lt;`
* `>` devient `&gt;`
* `"` devient `&quot;`
* `'` devient `&#039;`
* `&` devient `&amp;`

Lorsque le navigateur reçoit `&lt;script&gt;`, il ne crée pas d'élément `<script>` dans le DOM : il dessine simplement le mot `<script>` sous forme de texte inoffensif sur l'écran. L'exécution de code est totalement neutralisée.

---

### 🔴 C. Le Cross-Site Request Forgery (CSRF - Vote Forcé)

#### 1. Comment fonctionne l'attaque ?
Le CSRF repose sur la confiance aveugle qu'un serveur web accorde aux requêtes accompagnées d'un cookie de session valide.
1. Un étudiant se connecte légitimement sur VoteNow (`http://localhost:8000`). Son navigateur détient un cookie de session actif (`PHPSESSID`).
2. Sans se déconnecter, l'étudiant visite un autre site web piégé créé par un attaquant (ex: `demo_csrf.html` ou un faux site de cours).
3. Ce site tiers contient un formulaire invisible qui envoie automatiquement une requête POST vers `http://localhost:8000/api/votes.php` avec les paramètres `election_id=1` et `candidature_id=1`.
4. Le navigateur de la victime attache automatiquement le cookie de session `PHPSESSID` à cette requête sortante.
5. Le serveur VoteNow reçoit la requête, constate que la session de l'étudiant est active, et valide le vote au nom de l'étudiant sans que celui-ci n'ait jamais cliqué sur le bouton de vote.

#### 2. Démonstration Pratique
* **URL de test :** `http://localhost:8000/demo_csrf.html`
* **Prérequis :** Être connecté avec un compte étudiant (ex: `SN-2024-001` / `test123`).
* **Action :** Cliquer sur le déclencheur d'attaque dans la page externe.
* **Résultat visualisé :** Le vote est forcé et enregistré sans validation sur l'interface officielle.

#### 3. Comment la régler ?
Implémenter le patron de conception **Synchronizer Token Pattern** :
1. Générer un jeton cryptographiquement aléatoire et imprévisible lors de la création de la session :
   ```php
   if (empty($_SESSION['csrf_token'])) {
       $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   }
   ```
2. Inclure ce jeton dans tous les formulaires légitimes ou dans les en-têtes HTTP de requêtes AJAX (`X-CSRF-Token`).
3. Côté serveur, rejeter systématiquement toute requête modifiante (POST/PUT/DELETE) dont le jeton fourni ne correspond pas exactement à celui de la session :
   ```php
   $tokenRecu = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
   if (!hash_equals($_SESSION['csrf_token'], $tokenRecu)) {
       http_response_code(403);
       die(json_encode(['success' => false, 'message' => 'Jeton CSRF invalide ou manquant']));
   }
   ```

#### 4. Que signifie concrètement cette solution ?
Un site externe ne peut pas lire le contenu de la session ni les pages internes de VoteNow en raison de la politique de même origine du navigateur (**Same-Origin Policy**). Par conséquent, l'attaquant ne peut pas deviner ni intercepter ce jeton secret. Toute requête forgée par un tiers sera dépourvue du jeton valide et sera immédiatement bloquée par le serveur.

---

### 🛡️ D. Les Autres Défenses Indispensables Étudiées

| Mécanisme de Défense | Menace Ciblée | Explication Technique |
|---|---|---|
| **Hachage Bcrypt (`password_hash`)** | Vol de base de données | Algorithme à coût adaptatif avec sel cryptographique automatique rendant le cassage par tables arc-en-ciel (*rainbow tables*) impossible. |
| **Cookies HttpOnly & SameSite=Strict** | Vol de session (XSS) & CSRF | `HttpOnly` interdit à JavaScript de lire le cookie `PHPSESSID`. `SameSite=Strict` empêche le navigateur d'envoyer le cookie lors de requêtes initiées par des sites tiers. |
| **Régénération de Session (`session_regenerate_id`)** | Fixation de session | Change l'identifiant de session immédiatement après l'authentification, rendant inutilisable un ID pré-attribué par un attaquant. |
| **Limitation de Débit (Rate Limiting)** | Attaques par Brute-Force | Bloque les tentatives excessives (ex: maximum 10 tentatives de connexion par tranche de 5 minutes par adresse IP). |
| **Verrouillage de Compte** | Brute-force ciblé | Verrouille automatiquement le compte utilisateur pendant 15 minutes après 5 échecs de mot de passe consécutifs. |
| **Isolation .htaccess & Headers HTTP** | Clickjacking & Fuite de fichiers | En-têtes `X-Frame-Options: DENY` pour interdire l'intégration dans des iframes malveillantes, et blocage d'accès direct aux dossiers `/config` et `/logs`. |

---

## 🎓 4. Bilan Pédagogique : Ce Que Nous Avons Retenu

Ce projet pratique au sein du cours d'**Introduction à la Sécurité** a transformé des concepts théoriques abstraits en compétences pratiques concrètes :

1. **La sécurité n'est pas un ajout de fin de projet (*Security by Design*)** :  
   Il est complexe et risqué de tenter de sécuriser une application après coup. Les principes d'assainissement, de requêtes préparées et de gestion des droits doivent guider l'architecture dès le premier jour.
2. **Le postulat fondamental : Ne jamais faire confiance aux entrées utilisateur (*Never Trust User Input*)** :  
   Qu'il s'agisse d'un champ de formulaire, d'un paramètre GET, d'un en-tête HTTP ou d'un cookie, toute donnée extérieure doit être considérée comme potentiellement hostile jusqu'à ce qu'elle soit validée et typée côté serveur.
3. **La défense en profondeur (*Defense in Depth*)** :  
   Aucune mesure unique n'est infaillible. Une sécurité robuste repose sur l'empilement de couches complémentaires : base de données préparée, session étanche, en-têtes HTTP restrictifs, contrôle d'accès rigoureux et journalisation des événements suspects.
4. **L'importance des outils d'audit automatisés** :  
   L'utilisation d'**OWASP ZAP** nous a montré comment un auditeur ou un attaquant analyse une application. Cela nous donne les clés pour mener des audits préventifs dans nos futurs projets d'ingénierie logicielle.

---

*Document rédigé par l'équipe VoteNow - Licence 2 Informatique, Université Iba Der Thiam de Thiès (UIDT).*
