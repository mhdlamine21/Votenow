# Guide de contribution - VoteNow

## Bienvenue

Merci de l'intérêt pour VoteNow ! Toute contribution est la bienvenue.

## Comment contribuer

### 1. Fork et clone

    git clone https://github.com/mhdlamine21/Votenow.git
    cd Votenow
    git checkout -b feature/ma-nouvelle-feature

### 2. Conventions de code (signature)

- **PHP** : 2 espaces d'indentation, snake_case pour les variables, noms en français pour les variables métier
- **JS** : 2 espaces, camelCase pour les fonctions, double quotes, snake_case pour les classes CSS
- **CSS** : snake_case pour toutes les classes (pas de camelCase, pas de kebab-case)
- **SQL** : MAJUSCULES pour les mots-clés SQL, snake_case pour les noms de tables/colonnes

### 3. Conventions de commits

Utiliser le format Conventional Commits :

    feat: ajouter notifications email
    fix: corriger le bug de pagination sur Safari
    refactor: extraire VoteService depuis api/votes.php
    docs: mettre à jour le README avec les nouvelles routes
    test: ajouter tests PHPUnit pour l'authentification
    security: renforcer validation upload MIME
    chore: mettre à jour .gitignore

### 4. Pull Request

- Branche depuis `develop` (pas directement depuis `main`)
- Décrire clairement le changement et son impact
- Mentionner les issues liées avec `Fixes #123`
- S'assurer que les vérifications et tests passent avant de demander une review

### 5. Signaler un bug

Ouvrir une issue avec :
- Description du bug
- Étapes pour reproduire
- Comportement attendu vs observé
- Environnement (OS, PHP version, navigateur)

### 6. Proposer une feature

Ouvrir une issue avec le label `enhancement` avant de coder.
