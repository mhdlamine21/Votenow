#!/bin/bash
#  VoteNow v4 - Docker Entrypoint 
set -e

echo "VoteNow v4 - Demarrage..."

# Attendre que MySQL soit prêt
echo "Attente de MySQL..."
until mysqladmin ping -h "${DB_HOST:-mysql}" -u "${DB_USER:-votenow_user}" -p"${DB_PASS:-votenow_pass}" --silent 2>/dev/null; do
  echo "MySQL pas encore pret - attente 2s..."
  sleep 2
done
echo "MySQL pret."

# Copier .env.example si .env absent
if [ ! -f /var/www/html/.env ]; then
  cp /var/www/html/.env.example /var/www/html/.env
  echo ".env cree depuis .env.example"
fi

# Créer le dossier logs avec les bonnes permissions
mkdir -p /var/www/html/logs
chown -R www-data:www-data /var/www/html/logs
chmod 777 /var/www/html/logs

echo "VoteNow v4 pret sur http://localhost:${APP_PORT:-8080}"
echo "phpMyAdmin sur http://localhost:${PMA_PORT:-8081} (profil dev uniquement)"
echo "Comptes de test: superadmin/password | admin_st/password123 | SN-2024-001/test123"

# Lancer Apache
exec "$@"
