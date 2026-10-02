-- ============================================================
-- VoteNow - Données de test complètes
-- Tous les scénarios : élections, votes, résultats, 2ème tour
-- Importer APRÈS database.sql
-- ============================================================

USE votenow_db;

--  NETTOYAGE (optionnel, pour repartir de zéro) 
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE votes;
TRUNCATE TABLE participations;
TRUNCATE TABLE candidatures;
TRUNCATE TABLE elections;
TRUNCATE TABLE notifications;
TRUNCATE TABLE demandes_profil;
TRUNCATE TABLE logs;
TRUNCATE TABLE utilisateurs;
TRUNCATE TABLE etudiants_autorises;
DELETE FROM admins WHERE username != 'superadmin';
SET FOREIGN_KEY_CHECKS = 1;

--  RESET AUTO_INCREMENT 
ALTER TABLE utilisateurs AUTO_INCREMENT = 1;
ALTER TABLE elections AUTO_INCREMENT = 1;
ALTER TABLE candidatures AUTO_INCREMENT = 1;
ALTER TABLE votes AUTO_INCREMENT = 1;
ALTER TABLE participations AUTO_INCREMENT = 1;

--  ADMINS 
-- Mot de passe : "password123" pour tous les admins de test
INSERT INTO admins (username, password_hash, role, perimetre, ufr_id) VALUES
('admin_st',  '$2y$12$LcKwmBJq5qvEVMpk5tHQgeBXVVJ5vmKo0r47AMWjMGGJDHlhPi4Ni', 'admin', 'ufr',        1),
('admin_seg', '$2y$12$LcKwmBJq5qvEVMpk5tHQgeBXVVJ5vmKo0r47AMWjMGGJDHlhPi4Ni', 'admin', 'ufr',        2),
('admin_lsh', '$2y$12$LcKwmBJq5qvEVMpk5tHQgeBXVVJ5vmKo0r47AMWjMGGJDHlhPi4Ni', 'admin', 'ufr',        3),
('admin_univ','$2y$12$LcKwmBJq5qvEVMpk5tHQgeBXVVJ5vmKo0r47AMWjMGGJDHlhPi4Ni', 'admin', 'universite', NULL);

--  ÉTUDIANTS AUTORISÉS (liste importée par superadmin) 
-- UFR-ST Informatique
INSERT INTO etudiants_autorises (carte_identite, nom, prenom, ufr_id, filiere_id, niveau) VALUES
('SN-2024-001', 'NIANG',   'Mouhamadou Lamine', 1, 1, 'L3'),
('SN-2024-002', 'DIALLO',  'Amadou',            1, 1, 'L3'),
('SN-2024-003', 'BA',      'Fatou',             1, 1, 'L3'),
('SN-2024-004', 'SOW',     'Omar',              1, 1, 'L3'),
('SN-2024-005', 'FALL',    'Mariama',           1, 1, 'L3'),
('SN-2024-006', 'DIOP',    'Cheikh',            1, 1, 'L3'),
('SN-2024-007', 'MBAYE',   'Ibrahima',          1, 1, 'L3'),
('SN-2024-008', 'SARR',    'Aissatou',          1, 1, 'L3'),
('SN-2024-009', 'GUEYE',   'Pape',              1, 1, 'L3'),
('SN-2024-010', 'DIOUF',   'Ndèye',             1, 1, 'L3'),
-- UFR-ST Informatique L2
('SN-2024-011', 'SECK',    'Mamadou',           1, 1, 'L2'),
('SN-2024-012', 'BADJI',   'Marie',             1, 1, 'L2'),
('SN-2024-013', 'DIATTA',  'Paul',              1, 1, 'L2'),
-- UFR-ST Mathématiques
('SN-2024-014', 'COLY',    'Thierno',           1, 2, 'L3'),
('SN-2024-015', 'MANGA',   'Sophie',            1, 2, 'L3'),
-- UFR-SEG Économie
('SN-2024-016', 'FAYE',    'Ousmane',           2, 5, 'L3'),
('SN-2024-017', 'NDOYE',   'Rokhaya',           2, 5, 'L3'),
('SN-2024-018', 'DIENE',   'Alioune',           2, 5, 'L3'),
-- UFR-SEG Gestion
('SN-2024-019', 'TOURE',   'Fatoumata',         2, 6, 'M1'),
('SN-2024-020', 'CAMARA',  'Seydou',            2, 6, 'M1'),
-- UFR-LSH Lettres
('SN-2024-021', 'DIALLO',  'Anta',              3, 8, 'L2'),
('SN-2024-022', 'MENDY',   'Jacques',           3, 8, 'L2'),
-- UFR-SJP Droit
('SN-2024-023', 'MBOUP',   'Aminata',           4, 11, 'L3'),
('SN-2024-024', 'LAMINE',  'Youssou',           4, 11, 'L3');

--  COMPTES UTILISATEURS (mot de passe : "test123" pour tous) 
-- hash bcrypt de "test123" cost=12
INSERT INTO utilisateurs (carte_identite, nom, prenom, password_hash, ufr_id, filiere_id, niveau) VALUES
('SN-2024-001', 'NIANG',   'Mouhamadou Lamine', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-002', 'DIALLO',  'Amadou',            '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-003', 'BA',      'Fatou',             '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-004', 'SOW',     'Omar',              '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-005', 'FALL',    'Mariama',           '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-006', 'DIOP',    'Cheikh',            '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-007', 'MBAYE',   'Ibrahima',          '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-008', 'SARR',    'Aissatou',          '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-009', 'GUEYE',   'Pape',              '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-010', 'DIOUF',   'Ndèye',             '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, 'L3'),
('SN-2024-016', 'FAYE',    'Ousmane',           '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 5, 'L3'),
('SN-2024-017', 'NDOYE',   'Rokhaya',           '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 5, 'L3'),
('SN-2024-019', 'TOURE',   'Fatoumata',         '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 6, 'M1'),
('SN-2024-023', 'MBOUP',   'Aminata',           '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 11, 'L3');

--  ÉLECTIONS 

-- SCÉNARIO 1 : Élection ARCHIVÉE avec résultats complets (candidat gagnant clair)
INSERT INTO elections (id, admin_id, titre, description, scope, ufr_id, filiere_id, niveau, phase, resultats_publics, tour, date_vote_debut, date_vote_fin, archived_at)
VALUES (1, 2, 'Élection Délégués L3 Informatique 2023-2024',
  'Élection des représentants de la promotion L3 Informatique pour l\'année 2023-2024.',
  'niveau', 1, 1, 'L3', 'archivee', 'apres_cloture', 1,
  '2024-01-10 08:00:00', '2024-01-12 17:00:00', '2024-01-13 09:00:00');

-- SCÉNARIO 2 : Élection VOTE OUVERT (en cours) - scope filière Informatique
INSERT INTO elections (id, admin_id, titre, description, scope, ufr_id, filiere_id, niveau, phase, resultats_publics, tour, date_candidatures_debut, date_candidatures_fin, date_vote_debut, date_vote_fin)
VALUES (2, 2, 'Délégués L3 Informatique 2026-2026',
  'Votez pour vos représentants de promotion. 4 candidats en lice.',
  'filiere', 1, 1, NULL, 'vote', 'apres_cloture', 1,
  '2026-04-01 08:00:00', '2026-04-10 17:00:00',
  '2026-04-15 08:00:00', '2026-06-30 23:59:00');

-- SCÉNARIO 3 : Élection CANDIDATURES OUVERTES - scope UFR-ST
INSERT INTO elections (id, admin_id, titre, description, scope, ufr_id, filiere_id, niveau, phase, resultats_publics, tour, date_candidatures_debut, date_candidatures_fin, date_vote_debut, date_vote_fin)
VALUES (3, 2, 'Bureau des Étudiants UFR Sciences et Technologies 2026',
  'Élection du bureau des étudiants de l\'UFR Sciences et Technologies.',
  'ufr', 1, NULL, NULL, 'candidatures', 'apres_cloture', 1,
  '2026-05-01 08:00:00', '2026-06-10 17:00:00',
  '2026-06-15 08:00:00', '2026-06-25 23:59:00');

-- SCÉNARIO 4 : Élection UNIVERSITÉ ENTIÈRE - vote ouvert, résultats temps réel
INSERT INTO elections (id, admin_id, titre, description, scope, phase, resultats_publics, tour, date_vote_debut, date_vote_fin)
VALUES (4, 5, 'Représentants Étudiants au Conseil Académique UCAD 2026',
  'Élection des représentants étudiants au Conseil Académique de l\'UCAD.',
  'universite', 'vote', 'temps_reel', 1,
  '2026-05-15 08:00:00', '2026-06-30 23:59:00');

-- SCÉNARIO 5 : Élection VOTE FERMÉ - résultats disponibles, ex-aequo → 2ème tour
INSERT INTO elections (id, admin_id, titre, description, scope, ufr_id, filiere_id, niveau, phase, resultats_publics, tour, date_vote_debut, date_vote_fin)
VALUES (5, 2, 'Délégué L2 Informatique - Cas égalité 2ème tour',
  'Élection simulant un cas d\'égalité nécessitant un 2ème tour.',
  'niveau', 1, 1, 'L2', 'vote_ferme', 'apres_cloture', 1,
  '2026-03-01 08:00:00', '2026-03-15 17:00:00');

-- SCÉNARIO 6 : Élection 2ÈME TOUR EN COURS après égalité
INSERT INTO elections (id, admin_id, titre, description, scope, ufr_id, filiere_id, niveau, phase, resultats_publics, tour, date_vote_debut, date_vote_fin)
VALUES (6, 2, 'Délégué L3 Info - 2ème tour (égalité résolue)',
  'Deuxième tour suite à une égalité au premier tour.',
  'niveau', 1, 1, 'L3', 'vote', 'apres_cloture', 2,
  '2026-05-20 08:00:00', '2026-06-30 23:59:00');

-- SCÉNARIO 7 : Élection BROUILLON (pas encore visible)
INSERT INTO elections (id, admin_id, titre, description, scope, phase)
VALUES (7, 2, 'Préparation - Délégués M1 Informatique 2026-2026',
  'Brouillon en cours de configuration.',
  'niveau', 'brouillon');

--  CANDIDATURES 

-- Élection 1 (archivée) - résultats complets
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut) VALUES
(1, 1, 1, 'Mouhamadou Lamine NIANG', 'Pour une promotion unie et représentée',
  'Étudiant sérieux, impliqué dans la vie universitaire depuis la L1. Je m\'engage à défendre vos intérêts auprès des enseignants et de l\'administration.', 'validee'),
(2, 1, 2, 'Amadou DIALLO', 'L\'excellence au service de tous',
  'Délégué de TD depuis la L1. Je connais parfaitement les problématiques de notre promotion et je saurai les porter efficacement.', 'validee'),
(3, 1, 3, 'Fatou BA', 'Ensemble, construisons notre avenir académique',
  'Représentante associative active. Mon réseau et ma persévérance seront des atouts majeurs pour notre promotion.', 'validee');

-- Élection 2 (vote ouvert) - 4 candidats validés
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut) VALUES
(4, 2, 1, 'Mouhamadou Lamine NIANG', 'La voix de notre promotion',
  'Fort de mon expérience de délégué l\'année dernière, je souhaite continuer à vous représenter avec efficacité et transparence.', 'validee'),
(5, 2, 2, 'Amadou DIALLO', 'Innovation et représentation',
  'Passionné de programmation et de vie associative, je m\'engage à créer un pont solide entre étudiants et enseignants.', 'validee'),
(6, 2, 3, 'Fatou BA', 'Pour chaque étudiant(e)',
  'En tant que femme dans une filière à majorité masculine, je veux être une voix inclusive pour tous.', 'validee'),
(7, 2, 4, 'Omar SOW', 'Rigueur et proximité',
  'Meilleur étudiant de L2, je mets mes compétences au service de la promotion pour améliorer nos conditions d\'études.', 'validee');

-- Élection 3 (candidatures ouvertes) - candidatures en attente de validation
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut) VALUES
(8,  3, 5, 'Mariama FALL', 'Le changement commence par nous',
  'Active dans plusieurs associations, je souhaite apporter un regard nouveau à la gestion du bureau étudiant.', 'en_attente'),
(9,  3, 6, 'Cheikh DIOP',  'UFR-ST, notre maison commune',
  'Président sortant du club informatique de l\'UFR-ST, je connais les besoins de notre communauté.', 'en_attente'),
(10, 3, 7, 'Ibrahima MBAYE', 'Action, résultats, transparence',
  'Mon programme : améliorer les infrastructures numériques, négocier de meilleures salles de TP.', 'validee'),
(11, 3, 8, 'Aissatou SARR', 'Ensemble on va plus loin',
  'Coordinatrice de projets étudiants depuis 2 ans, je sais fédérer et obtenir des résultats concrets.', 'refusee');

-- Élection 4 (université entière) - candidatures
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut) VALUES
(12, 4, 1, 'Mouhamadou Lamine NIANG', 'La parole étudiante au cœur du conseil', 'Représenter l\'ensemble des étudiants de l\'UCAD avec objectivité et engagement.', 'validee'),
(13, 4, 11, 'Ousmane FAYE',  'Economie et justice pour tous', 'Étudiant en Économie L3, je défendrai les droits de tous les étudiants sans distinction.', 'validee'),
(14, 4, 13, 'Aminata MBOUP', 'Droit et dignité étudiante',   'Future avocate, je maîtrise le cadre réglementaire universitaire pour mieux le faire respecter.', 'validee');

-- Élection 5 (égalité 1er tour) - 2 candidats à égalité
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut, qualifie_tour2) VALUES
(15, 5, NULL, 'Candidat Alpha', 'Je suis le meilleur choix', 'Candidat sérieux et impliqué.', 'validee', TRUE),
(16, 5, NULL, 'Candidat Bêta',  'Votre voix, ma priorité',   'Toujours là pour vous défendre.', 'validee', TRUE),
(17, 5, NULL, 'Candidat Gamma', 'La nouveauté au service du changement', 'Nouveau visage, idées fraîches.', 'validee', FALSE);

-- Élection 6 (2ème tour en cours) - seulement les 2 ex-aequo qualifiés
INSERT INTO candidatures (id, election_id, utilisateur_id, nom_complet, slogan, description, statut, qualifie_tour2) VALUES
(18, 6, 2, 'Amadou DIALLO', 'Votre délégué, votre voix', 'Candidat au 2ème tour après égalité au 1er.', 'validee', TRUE),
(19, 6, 3, 'Fatou BA',      'Pour chaque étudiant(e)',   'Candidate au 2ème tour après égalité au 1er.', 'validee', TRUE);

--  VOTES ÉLECTION 1 (ARCHIVÉE) - résultats : Niang gagne largement 
-- Cand 1 (Niang) : 6 voix - Cand 2 (Diallo) : 2 voix - Cand 3 (Ba) : 2 voix
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(1,1,1),(1,2,1),(1,3,1),(1,4,1),(1,5,1),(1,6,1),(1,7,1),(1,8,1),(1,9,1),(1,10,1);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(1,1,1),(1,1,1),(1,1,1),(1,1,1),(1,1,1),(1,1,1), -- Niang : 6 voix
(1,2,1),(1,2,1),                                   -- Diallo : 2 voix
(1,3,1),(1,3,1);                                   -- Ba : 2 voix

--  VOTES ÉLECTION 2 (VOTE OUVERT) - votes partiels de 4 étudiants sur 10 
-- Cand 4 (Niang) : 2 voix - Cand 5 (Diallo) : 1 voix - Cand 7 (Sow) : 1 voix
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(2,5,1),(2,6,1),(2,7,1),(2,8,1);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(2,4,1),(2,4,1),  -- Niang : 2 voix
(2,5,1),          -- Diallo : 1 voix
(2,7,1);          -- Sow : 1 voix

--  VOTES ÉLECTION 4 (UNIVERSITÉ) - votes sur plusieurs UFR 
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(4,1,1),(4,2,1),(4,11,1),(4,12,1),(4,13,1),(4,14,1);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(4,12,1),(4,12,1),(4,12,1), -- Niang : 3 voix
(4,13,1),(4,13,1),          -- Faye : 2 voix
(4,14,1);                   -- Mboup : 1 voix

--  VOTES ÉLECTION 5 (ÉGALITÉ 1er TOUR) - Alpha et Bêta à égalité 
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(5,1,1),(5,2,1),(5,3,1),(5,4,1),(5,5,1),(5,6,1);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(5,15,1),(5,15,1),(5,15,1), -- Alpha : 3 voix
(5,16,1),(5,16,1),(5,16,1), -- Bêta : 3 voix (ÉGALITÉ)
(5,17,1);                   -- NOTE: 1 vote Gamma mais 3+3=6 sans Gamma - ok

--  VOTES ÉLECTION 6 (2ÈME TOUR EN COURS) - votes partiels 
-- Votes du 1er tour déjà enregistrés (pour référence historique)
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(6,1,1),(6,2,1),(6,3,1),(6,4,1),(6,5,1),(6,6,1),(6,7,1),(6,8,1);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(6,18,1),(6,18,1),(6,18,1),(6,18,1), -- Diallo tour 1 : 4 voix
(6,19,1),(6,19,1),(6,19,1),(6,19,1); -- Ba tour 1 : 4 voix (ÉGALITÉ → 2ème tour lancé)

-- 2ème tour : quelques votes déjà enregistrés (pas encore tous)
INSERT INTO participations (election_id, utilisateur_id, tour) VALUES
(6,1,2),(6,3,2),(6,5,2);

INSERT INTO votes (election_id, candidature_id, tour) VALUES
(6,18,2),(6,18,2),  -- Diallo tour 2 : 2 voix
(6,19,2);           -- Ba tour 2 : 1 voix

--  DEMANDES DE CHANGEMENT DE PROFIL 
-- En attente (à traiter par le superadmin)
INSERT INTO demandes_profil (utilisateur_id, ufr_id, filiere_id, niveau, statut)
VALUES (10, 1, 1, 'M1', 'en_attente');

-- Approuvée (historique)
INSERT INTO demandes_profil (utilisateur_id, ufr_id, filiere_id, niveau, statut)
VALUES (9, 1, 2, 'L3', 'approuvee');

--  NOTIFICATIONS 
INSERT INTO notifications (utilisateur_id, titre, message, lue, lien) VALUES
(1, 'Votre candidature a été validée', 'Votre candidature pour "Délégués L3 Informatique 2026-2026" a été validée par l\'administration. Bonne chance !', 0, 'election=2'),
(1, 'Vote ouvert - Délégués L3 Info', 'L\'élection "Délégués L3 Informatique 2026-2026" est maintenant ouverte au vote. Votez avant le 30 juin 2025.', 0, 'election=2'),
(2, 'Vote ouvert - Conseil Académique', 'L\'élection des représentants au Conseil Académique est ouverte à tous les étudiants.', 0, 'election=4'),
(3, 'Candidature - phase ouverte', 'La phase de candidatures pour le Bureau UFR-ST est ouverte. Déposez votre candidature avant le 10 juin.', 1, 'election=3'),
(4, 'Vote ouvert - Délégués L3 Info', 'L\'élection est maintenant ouverte au vote.', 0, 'election=2'),
(11, 'Candidature refusée', 'Votre candidature pour "Bureau UFR-ST 2026" a été refusée. Motif : Dossier incomplet - slogan trop court.', 0, 'election=3'),
(10, 'Demande de changement de profil', 'Votre demande de changement de niveau (L3 → M1) est en cours d\'examen par l\'administration.', 0, NULL);

--  LOGS (quelques entrées pour démonstration) 
INSERT INTO logs (type, action, utilisateur_id, admin_id, ip) VALUES
('login',       'Connexion: SN-2024-001',   1,    NULL, '192.168.1.x.x'),
('login',       'Connexion: SN-2024-002',   2,    NULL, '192.168.1.x.x'),
('login_admin', 'Admin connecté: admin_st', NULL, 2,    '10.0.0.x.x'),
('vote',        'Vote enregistré election #2 tour 1', 5, NULL, '192.168.1.x.x'),
('vote',        'Vote enregistré election #2 tour 1', 6, NULL, '192.168.1.x.x'),
('login_failed','Tentative échouée pour: SN-9999-000', NULL, NULL, '10.10.x.x'),
('register',    'Inscription: SN-2024-016', NULL, NULL, '192.168.1.x.x'),
('candidature', 'Candidature déposée pour election #2', 1, NULL, '192.168.1.x.x');
