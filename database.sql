CREATE DATABASE IF NOT EXISTS votenow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE votenow_db;

CREATE TABLE IF NOT EXISTS settings (
    cle VARCHAR(100) PRIMARY KEY,
    valeur TEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin') DEFAULT 'admin',
    perimetre ENUM('universite','ufr','filiere') DEFAULT 'universite',
    ufr_id INT DEFAULT NULL,
    filiere_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ufr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ufr_id INT NOT NULL,
    nom VARCHAR(200) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ufr_id) REFERENCES ufr(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS niveaux_filiere (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiere_id INT NOT NULL,
    niveau VARCHAR(20) NOT NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_niveau (filiere_id, niveau)
);

CREATE TABLE IF NOT EXISTS etudiants_autorises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carte_identite VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    ufr_id INT DEFAULT NULL,
    filiere_id INT DEFAULT NULL,
    niveau VARCHAR(20) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ufr_id) REFERENCES ufr(id) ON DELETE SET NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carte_identite VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    ufr_id INT DEFAULT NULL,
    filiere_id INT DEFAULT NULL,
    niveau VARCHAR(20) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ufr_id) REFERENCES ufr(id) ON DELETE SET NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS demandes_profil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    ufr_id INT DEFAULT NULL,
    filiere_id INT DEFAULT NULL,
    niveau VARCHAR(20) DEFAULT NULL,
    statut ENUM('en_attente','approuvee','refusee') DEFAULT 'en_attente',
    motif_refus TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (ufr_id) REFERENCES ufr(id) ON DELETE SET NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS elections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    scope ENUM('universite','ufr','filiere','niveau') DEFAULT 'universite',
    ufr_id INT DEFAULT NULL,
    filiere_id INT DEFAULT NULL,
    niveau VARCHAR(20) DEFAULT NULL,
    phase ENUM('brouillon','candidatures','candidatures_fermees','vote','vote_ferme','archivee') DEFAULT 'brouillon',
    resultats_publics ENUM('temps_reel','apres_cloture') DEFAULT 'apres_cloture',
    date_candidatures_debut DATETIME DEFAULT NULL,
    date_candidatures_fin DATETIME DEFAULT NULL,
    date_vote_debut DATETIME DEFAULT NULL,
    date_vote_fin DATETIME DEFAULT NULL,
    tour INT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    FOREIGN KEY (ufr_id) REFERENCES ufr(id) ON DELETE SET NULL,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS candidatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    utilisateur_id INT DEFAULT NULL,
    nom_complet VARCHAR(200) NOT NULL,
    slogan VARCHAR(150) DEFAULT NULL,
    description TEXT,
    photo LONGTEXT,
    programme LONGTEXT,
    statut ENUM('en_attente','validee','refusee','retiree') DEFAULT 'en_attente',
    motif_refus TEXT,
    qualifie_tour2 BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_candidature (election_id, utilisateur_id)
);

CREATE TABLE IF NOT EXISTS participations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    tour INT NOT NULL DEFAULT 1,
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_participation (election_id, utilisateur_id, tour)
);

CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL,
    candidature_id INT NOT NULL,
    tour INT NOT NULL DEFAULT 1,
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (candidature_id) REFERENCES candidatures(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS decision_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_id INT NOT NULL UNIQUE,
    candidature_id INT NOT NULL,
    motif TEXT NOT NULL,
    admin_id INT NOT NULL,
    decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (candidature_id) REFERENCES candidatures(id),
    FOREIGN KEY (admin_id) REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    titre VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    lue BOOLEAN DEFAULT FALSE,
    lien VARCHAR(200) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    action TEXT NOT NULL,
    utilisateur_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (cle, valeur) VALUES
('app_name', 'VoteNow'),
('universite_nom', 'Université Cheikh Anta Diop de Dakar'),
('universite_logo', ''),
('couleur_principale', '#3b82f6'),
('message_accueil', 'Bienvenue sur la plateforme de vote universitaire'),
('contact_admin', 'admin@ucad.edu.sn');

-- Mot de passe: "password"
INSERT IGNORE INTO admins (username, password_hash, role, perimetre) VALUES
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 'universite');

INSERT IGNORE INTO ufr (id, nom, code, description) VALUES
(1, 'UFR Sciences et Technologies', 'UFR-ST', 'Informatique, Mathématiques, Physique, Chimie'),
(2, 'UFR Sciences Économiques et Gestion', 'UFR-SEG', 'Économie, Gestion, Finance'),
(3, 'UFR Lettres et Sciences Humaines', 'UFR-LSH', 'Lettres, Histoire, Géographie'),
(4, 'UFR Sciences Juridiques et Politiques', 'UFR-SJP', 'Droit, Sciences Politiques');

INSERT IGNORE INTO filieres (id, ufr_id, nom) VALUES
(1,1,'Informatique'),(2,1,'Mathématiques'),(3,1,'Physique'),(4,1,'Chimie'),
(5,2,'Économie'),(6,2,'Gestion'),(7,2,'Finance'),
(8,3,'Lettres Modernes'),(9,3,'Histoire'),(10,3,'Philosophie'),
(11,4,'Droit Privé'),(12,4,'Droit Public'),(13,4,'Sciences Politiques');

INSERT IGNORE INTO niveaux_filiere (filiere_id, niveau) VALUES
(1,'L1'),(1,'L2'),(1,'L3'),(1,'M1'),(1,'M2'),
(2,'L1'),(2,'L2'),(2,'L3'),(2,'M1'),(2,'M2'),
(3,'L1'),(3,'L2'),(3,'L3'),
(4,'L1'),(4,'L2'),(4,'L3'),
(5,'L1'),(5,'L2'),(5,'L3'),(5,'M1'),(5,'M2'),
(6,'L1'),(6,'L2'),(6,'L3'),(6,'M1'),(6,'M2'),
(7,'L1'),(7,'L2'),(7,'L3'),(7,'M1'),(7,'M2'),
(8,'L1'),(8,'L2'),(8,'L3'),
(9,'L1'),(9,'L2'),(9,'L3'),
(10,'L1'),(10,'L2'),(10,'L3'),
(11,'L1'),(11,'L2'),(11,'L3'),(11,'M1'),(11,'M2'),
(12,'L1'),(12,'L2'),(12,'L3'),(12,'M1'),(12,'M2'),
(13,'L1'),(13,'L2'),(13,'L3'),(13,'M1'),(13,'M2');

INSERT IGNORE INTO etudiants_autorises (carte_identite, nom, prenom, ufr_id, filiere_id, niveau) VALUES
('SN-001','NIANG','Mouhamadou Lamine',1,1,'L3'),
('SN-002','DIALLO','Amadou',1,1,'L3'),
('SN-003','BA','Fatou',1,1,'L3'),
('SN-004','SOW','Omar',1,1,'L3'),
('SN-005','FALL','Aissatou',2,5,'L2'),
('SN-006','DIOP','Cheikh',2,6,'M1');
