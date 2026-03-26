#!/bin/bash
#  VoteNow v4 - Script de déploiement production 
# Usage : ./deploy.sh [options]
# Options :
#   --env=production|staging      Environnement cible
#   --domain=votenow.monsite.com  Domaine de l'application
#   --docker                      Déployer avec Docker (défaut)
#   --apache                      Déployer sur Apache natif
#   --skip-backup                 Pas de backup avant déploiement
#   --force                       Ne pas demander de confirmation

set -e

#  Couleurs 
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log()     { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
section() { echo -e "\n${BLUE}=== $1 ===${NC}"; }

#  Valeurs par défaut 
ENV="production"
DOMAIN="localhost"
DEPLOY_METHOD="docker"
SKIP_BACKUP=false
FORCE=false
APP_DIR="/var/www/votenow"
BACKUP_DIR="/var/backups/votenow"

#  Parser les arguments 
for arg in "$@"; do
  case $arg in
    --env=*)      ENV="${arg#*=}" ;;
    --domain=*)   DOMAIN="${arg#*=}" ;;
    --docker)     DEPLOY_METHOD="docker" ;;
    --apache)     DEPLOY_METHOD="apache" ;;
    --skip-backup) SKIP_BACKUP=true ;;
    --force)      FORCE=true ;;
    *)            warn "Argument inconnu : $arg" ;;
  esac
done

#  Affichage du plan 
section "VoteNow v4 - Déploiement"
log "Environnement : $ENV"
log "Domaine       : $DOMAIN"
log "Méthode       : $DEPLOY_METHOD"
log "Dossier app   : $APP_DIR"
log "Backup        : $( [ "$SKIP_BACKUP" = true ] && echo "désactivé" || echo "$BACKUP_DIR" )"
echo ""

#  Confirmation 
if [ "$FORCE" = false ]; then
  read -p "Continuer le déploiement ? (y/N) " -n 1 -r
  echo ""
  if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log "Déploiement annulé."
    exit 0
  fi
fi

#  Vérifications prérequis 
section "Vérifications"

command -v git  >/dev/null 2>&1 || error "git non trouvé"
log "git trouvé"

if [ "$DEPLOY_METHOD" = "docker" ]; then
  command -v docker >/dev/null 2>&1 || error "docker non trouvé"
  docker compose version >/dev/null 2>&1 || error "docker compose non trouvé"
  log "docker + docker compose trouvés"
else
  command -v php   >/dev/null 2>&1 || error "php non trouvé"
  command -v mysql >/dev/null 2>&1 || error "mysql non trouvé"
  log "php + mysql trouvés"
fi

#  Backup 
if [ "$SKIP_BACKUP" = false ] && [ -f "$APP_DIR/.env" ]; then
  section "Backup avant déploiement"
  mkdir -p "$BACKUP_DIR"
  BACKUP_DATE=$(date +%Y%m%d_%H%M%S)

  # Backup du code
  if [ -d "$APP_DIR" ]; then
    tar -czf "$BACKUP_DIR/code_${BACKUP_DATE}.tar.gz" -C "$(dirname $APP_DIR)" "$(basename $APP_DIR)" 2>/dev/null
    log "Backup code : $BACKUP_DIR/code_${BACKUP_DATE}.tar.gz"
  fi

  # Backup de la base de données
  if [ -f "$APP_DIR/.env" ]; then
    source "$APP_DIR/.env" 2>/dev/null || true
    if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
      mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        > "$BACKUP_DIR/db_${BACKUP_DATE}.sql" 2>/dev/null \
        && log "Backup DB : $BACKUP_DIR/db_${BACKUP_DATE}.sql" \
        || warn "Backup DB échoué (non bloquant)"
    fi
  fi

  # Garder seulement les 10 derniers backups
  ls -t "$BACKUP_DIR"/code_*.tar.gz 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null
  ls -t "$BACKUP_DIR"/db_*.sql      2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null
fi

#  Déploiement Docker 
if [ "$DEPLOY_METHOD" = "docker" ]; then
  section "Déploiement Docker"

  # Créer le dossier si nécessaire
  if [ ! -d "$APP_DIR" ]; then
    log "Clonage initial du repo..."
    git clone . "$APP_DIR"
  fi

  cd "$APP_DIR"

  # Pull dernières modifications
  log "Pull Git..."
  git pull origin main

  # Copier .env si absent
  if [ ! -f .env ]; then
    cp .env.example .env
    warn ".env créé depuis .env.example - À CONFIGURER !"
    warn "Éditer $APP_DIR/.env avant de continuer."
    if [ "$FORCE" = false ]; then
      read -p "Appuyer sur Entrée après configuration de .env..."
    fi
  fi

  # Build et démarrage
  log "Build Docker..."
  docker compose build --no-cache php

  log "Démarrage des conteneurs..."
  docker compose up -d

  log "Attente MySQL..."
  sleep 15

  log "Initialisation/vérification DB..."
  docker compose exec -T php php scripts/setup.php

  log "Déploiement Docker terminé !"

#  Déploiement Apache natif 
else
  section "Déploiement Apache natif"

  # Créer le dossier si nécessaire
  mkdir -p "$APP_DIR"

  # Copier les fichiers
  log "Copie des fichiers..."
  rsync -av --exclude='.git' --exclude='node_modules' --exclude='*.log' \
    ./ "$APP_DIR/"

  # Permissions
  log "Configuration des permissions..."
  chown -R www-data:www-data "$APP_DIR"
  chmod -R 755 "$APP_DIR"
  chmod -R 777 "$APP_DIR/logs"
  chmod 640 "$APP_DIR/.env" 2>/dev/null || true

  # Copier .env si absent
  if [ ! -f "$APP_DIR/.env" ]; then
    cp .env.example "$APP_DIR/.env"
    warn ".env créé depuis .env.example - À CONFIGURER !"
  fi

  # Configuration Apache VirtualHost
  log "Configuration Apache..."
  cat > /etc/apache2/sites-available/votenow.conf << APACHECONF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_DIR

    <Directory $APP_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/apache2/votenow_error.log
    CustomLog /var/log/apache2/votenow_access.log combined
</VirtualHost>
APACHECONF

  a2ensite votenow.conf
  a2enmod rewrite headers
  systemctl reload apache2
  log "Apache configuré pour $DOMAIN"

  # Init DB
  source "$APP_DIR/.env" 2>/dev/null || true
  log "Initialisation DB..."
  php "$APP_DIR/scripts/setup.php"

  log "Déploiement Apache terminé !"
fi

#  Résumé final 
section "Déploiement terminé"
log "Application disponible sur : http://$DOMAIN"
if [ "$DEPLOY_METHOD" = "docker" ]; then
  log "phpMyAdmin : http://$DOMAIN:8081 (profil dev)"
fi
log "Logs : $APP_DIR/logs/"
warn "N'oubliez pas de :"
warn "  1. Configurer HTTPS (Let's Encrypt : certbot --apache -d $DOMAIN)"
warn "  2. Changer les mots de passe par défaut"
warn "  3. Configurer les sauvegardes automatiques MySQL"
warn "  4. Retirer test_data.sql du serveur de production"
