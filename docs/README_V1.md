# 🗳️ VoteNow (Version 1 - Décembre 2025)

> **Projet Académique :** Examen Semestre 3 - Licence 2 Informatique  
> **Cours :** Développement Web 1  
> **Année Universitaire :** 2025 - 2026  
> **Auteur Unique :** [Mouhamadou Lamine Niang](mailto:mouhamedlniang@gmail.com)  

---

## 📌 Présentation du Projet Initial (V1)

**VoteNow V1** est une application web de vote électronique conçue et développée individuellement par **[Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com)** dans le cadre de l'examen pratique du cours de **Développement Web 1**.

Ce sujet d'examen était un travail **individuel attribué à l'ensemble de la promotion de Licence 2**. Chaque étudiant devait donc réaliser de son côté sa propre plateforme de vote.

Le cahier des charges académique imposait une réalisation **exclusivement côté client**, sans langage backend ni serveur de base de données :
* **HTML5 sémantique** pour la structure complète de la plateforme ;
* **CSS3 moderne** (Flexbox, CSS Grid, variables CSS, transitions fluides, thème sombre/clair) pour une expérience utilisateur soignée et responsive (mobile-first) ;
* **JavaScript ES6+ Vanilla** pour l'intégralité de la logique applicative (sans framework lourd) ;
* **LocalStorage du navigateur** pour la persistance locale de l'ensemble des données.

---

## ✨ Fonctionnalités Réalisées en V1

* **Catalogue des scrutins et candidats** : Consultation des élections universitaires, affichage des fiches de candidats avec slogans et programmes.
* **Système de vote interactif** : Sélection de candidat, confirmation modale de vote et comptabilisation en temps réel.
* **Contrôle d'unicité de vote via LocalStorage** : Vérification locale empêchant un même navigateur d'émettre plusieurs votes pour un même scrutin.
* **Simulateur de résultats et graphiques** : Dépouillement dynamique et visualisation sous forme de barres de progression animées en CSS/JS.
* **Interface Administration mockée** : Espace d'administration en frontend permettant de prévisualiser l'ajout d'élections et la gestion des candidatures.
* **Design Responsive & Dark Mode** : Bascule instantanée entre mode sombre et mode clair avec persistance dans le `localStorage`.

---

## 🛠️ Stack Technique V1

| Composant | Technologie | Rôle |
|---|---|---|
| **Structure** | HTML5 | Sémantique, formulaires et accessibilité |
| **Mise en page & Thème** | CSS3 Vanilla | Glassmorphism, animations et adaptabilité mobile |
| **Logique & Dynamisme** | JavaScript (ES6+) | Manipulation du DOM et gestion événementielle |
| **Persistance des données** | API Web Storage (`localStorage`) | Sauvegarde des états de vote et des scrutins |

---

## ⚖️ Limites Identifiées & Choix pour le Projet de Sécurité (S4)

Bien que fonctionnelle, ergonomique et validée avec succès pour l'examen de Développement Web 1, cette première mouture présentait des limites intrinsèques à une architecture 100% frontend :
1. **Sécurité et intégrité des votes** : Les données dans `localStorage` sont modifiables par l'utilisateur via les outils de développement du navigateur.
2. **Absence de contrôle serveur** : Impossibilité d'authentifier formellement un électeur ou de garantir le secret de l'isoloir à l'échelle d'un réseau universitaire.
3. **Persistance locale isolée** : Chaque machine possédait ses propres données locales.

Plus tard, au **Semestre 4**, l'enseignant du cours d'**Introduction à la Sécurité** a attribué par groupe de 3 le sujet « Sécurité d'une application web » sans imposer d'application particulière. Notre groupe ([Mouhamadou Lamine NIANG](mailto:mouhamedlniang@gmail.com), [Papa Mangone GUEYE](mailto:pmangone.gueye@univ-thies.sn) et [Mamadou SY](mailto:mamadou.sy7@univ-thies.sn)) a alors choisi de retenir la version VoteNow de Mouhamadou Lamine NIANG comme point de départ pour la faire évoluer avec un backend PHP / MySQL et réaliser une étude approfondie de sécurité web (voir le [README principal](../README.md), [BILAN_SECURITE.md](../BILAN_SECURITE.md) et [SECURITY.md](../SECURITY.md)).

---

## 👤 Auteur

* **Mouhamadou Lamine Niang** - *Conception & Développement V1*  
  Email : [mouhamedlniang@gmail.com](mailto:mouhamedlniang@gmail.com)
