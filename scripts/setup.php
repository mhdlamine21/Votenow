<?php
/**
 * VoteNow - script d'initialisation
 * Usage : php scripts/setup.php
 */

echo "\n[VoteNow] Initialisation et Setup\n\n";

// Charger variables d'environnement
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
  foreach (file($env_file) as $line) {
    $line = trim($line);
    if ($line && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
      [$key, $val] = explode('=', $line, 2);
      putenv(trim($key) . '=' . trim($val));
    }
  }
}

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'votenow_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Test connexion
echo "1. Test connexion MySQL ({$host})... ";
try {
  $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
  echo "OK\n";
} catch (Exception $e) {
  echo "ERREUR: " . $e->getMessage() . "\n";
  exit(1);
}

// Vérifier les tables
echo "2. Verification des tables... ";
$stmt  = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$expected = ['ufr','filieres','niveaux_filiere','etudiants_autorises','utilisateurs',
             'admins','elections','candidatures','votes','participations',
             'notifications','logs','demandes_profil','parametres'];
$missing = array_diff($expected, $tables);
if (empty($missing)) {
  echo "OK (" . count($tables) . " tables)\n";
} else {
  echo "MANQUANTES: " . implode(', ', $missing) . "\n";
  echo "Essai d'import du schema...\n";
  $sql = file_get_contents(__DIR__ . '/../database.sql');
  $pdo->exec($sql);
  echo "Schema importe.\n";
}

// Vérifier données de test
echo "3. Verification donnees de test... ";
$count = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
if ($count > 0) {
  echo "OK ({$count} admins)\n";
} else {
  echo "0 admins - import des donnees de test...\n";
  $sql = file_get_contents(__DIR__ . '/../test_data.sql');
  $pdo->exec($sql);
  echo "Donnees importees.\n";
}

// Vérifier super admin
echo "4. Verification compte superadmin... ";
$admin = $pdo->query("SELECT id FROM admins WHERE username='superadmin' LIMIT 1")->fetch();
echo $admin ? "OK\n" : "ABSENT (verifier test_data.sql)\n";

echo "\n[VoteNow] Configuration terminee avec succes\n";
echo "Application disponible sur http://localhost:8080\n";
echo "Comptes de test :\n";
echo "  - superadmin / password\n";
echo "  - admin_st / password123\n";
echo "  - SN-2024-001 / test123 (etudiant)\n\n";
