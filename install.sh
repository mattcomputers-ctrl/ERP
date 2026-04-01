#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────
# Precision Ink ERP – Installer / Updater
# Must be run as root (or via sudo).
# ─────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Colours ──────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; NC='\033[0m'

APP_DIR="/var/www/precision-erp"
LOG_FILE="${APP_DIR}/logs/install.log"
CRON_FILE="/etc/cron.d/precision-erp"

info()    { echo -e "${CYAN}[INFO]${NC}  $*" | tee -a "$LOG_FILE"; }
success() { echo -e "${GREEN}[OK]${NC}    $*" | tee -a "$LOG_FILE"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*" | tee -a "$LOG_FILE"; }
fail()    { echo -e "${RED}[FAIL]${NC}  $*" | tee -a "$LOG_FILE"; exit 1; }

# ── Pre-flight ───────────────────────────────────────────────────────
[[ $EUID -ne 0 ]] && fail "This script must be run as root (sudo ./install.sh)"

mkdir -p "$(dirname "$LOG_FILE")"
echo "── Install started: $(date -Iseconds) ──" >> "$LOG_FILE"

# ── Detect mode ──────────────────────────────────────────────────────
if [[ -f "${APP_DIR}/config/config.php" ]]; then
    MODE="update"
    info "Existing installation detected – running UPDATE"
else
    MODE="install"
    info "No config found – running FRESH INSTALL"
fi

# ═════════════════════════════════════════════════════════════════════
# FRESH INSTALL
# ═════════════════════════════════════════════════════════════════════
if [[ "$MODE" == "install" ]]; then

    # ── System packages ──────────────────────────────────────────────
    info "Updating package lists…"
    apt-get update -qq >> "$LOG_FILE" 2>&1

    info "Installing Apache, MySQL, PHP 8.2 and extensions…"
    apt-get install -y -qq \
        apache2 \
        mysql-server \
        php8.2 \
        libapache2-mod-php8.2 \
        php8.2-mysql \
        php8.2-mbstring \
        php8.2-xml \
        php8.2-curl \
        php8.2-zip \
        php8.2-gd \
        php8.2-intl \
        php8.2-bcmath \
        unzip git curl >> "$LOG_FILE" 2>&1
    success "System packages installed"

    # ── Composer ─────────────────────────────────────────────────────
    if ! command -v composer &>/dev/null; then
        info "Installing Composer globally…"
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >> "$LOG_FILE" 2>&1
        success "Composer installed"
    else
        success "Composer already present"
    fi

    # ── Application files ────────────────────────────────────────────
    info "Running composer install…"
    cd "$APP_DIR"
    composer install --no-interaction --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1
    success "Composer dependencies installed"

    # ── MySQL database & user ────────────────────────────────────────
    read -rp "Database name [precision_erp]: " DB_NAME
    DB_NAME="${DB_NAME:-precision_erp}"
    read -rp "Database user [erp_user]: "     DB_USER
    DB_USER="${DB_USER:-erp_user}"
    read -rsp "Database password: "           DB_PASS; echo
    read -rp "Database host [localhost]: "    DB_HOST
    DB_HOST="${DB_HOST:-localhost}"

    info "Creating database and user…"
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>> "$LOG_FILE"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';" 2>> "$LOG_FILE"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}'; FLUSH PRIVILEGES;" 2>> "$LOG_FILE"
    success "Database '${DB_NAME}' and user '${DB_USER}' ready"

    # ── Migration tracking table ─────────────────────────────────────
    mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" <<-SQL
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename   VARCHAR(255)  NOT NULL UNIQUE,
            applied_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
SQL
    success "schema_migrations table ensured"

    # ── Run all migrations ───────────────────────────────────────────
    info "Running migrations…"
    for file in "${APP_DIR}"/migrations/*.sql; do
        [[ -f "$file" ]] || continue
        base="$(basename "$file")"
        already=$(mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" \
            -sse "SELECT COUNT(*) FROM schema_migrations WHERE filename='${base}';")
        if [[ "$already" -eq 0 ]]; then
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" < "$file" 2>> "$LOG_FILE"
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" \
                -e "INSERT INTO schema_migrations (filename) VALUES ('${base}');"
            success "  Applied: ${base}"
        fi
    done
    success "All migrations applied"

    # ── Generate config.php ──────────────────────────────────────────
    read -rp "Application URL [http://localhost]: " APP_URL
    APP_URL="${APP_URL:-http://localhost}"
    read -rp "Application name [Precision Ink ERP]: " APP_NAME
    APP_NAME="${APP_NAME:-Precision Ink ERP}"
    API_KEY="$(openssl rand -hex 32)"
    read -rp "Backup path [${APP_DIR}/storage/backups]: " BACKUP_PATH
    BACKUP_PATH="${BACKUP_PATH:-${APP_DIR}/storage/backups}"
    read -rp "Session timeout in seconds [1800]: " SESSION_TIMEOUT
    SESSION_TIMEOUT="${SESSION_TIMEOUT:-1800}"

    sed -e "s|{{DB_HOST}}|${DB_HOST}|g" \
        -e "s|{{DB_NAME}}|${DB_NAME}|g" \
        -e "s|{{DB_USER}}|${DB_USER}|g" \
        -e "s|{{DB_PASS}}|${DB_PASS}|g" \
        -e "s|{{APP_URL}}|${APP_URL}|g" \
        -e "s|{{APP_NAME}}|${APP_NAME}|g" \
        -e "s|{{API_KEY}}|${API_KEY}|g" \
        -e "s|{{BACKUP_PATH}}|${BACKUP_PATH}|g" \
        -e "s|{{SESSION_TIMEOUT}}|${SESSION_TIMEOUT}|g" \
        "${APP_DIR}/config/config.php.template" > "${APP_DIR}/config/config.php"
    success "config.php generated"

    # ── Create admin user ────────────────────────────────────────────
    read -rp "Admin username [admin]: " ADMIN_USER
    ADMIN_USER="${ADMIN_USER:-admin}"
    read -rsp "Admin password: " ADMIN_PASS; echo
    ADMIN_HASH="$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")"

    mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" <<-SQL
        CREATE TABLE IF NOT EXISTS users (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username    VARCHAR(100) NOT NULL UNIQUE,
            password    VARCHAR(255) NOT NULL,
            role        VARCHAR(50)  NOT NULL DEFAULT 'admin',
            active      TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
        INSERT INTO users (username, password, role)
        VALUES ('${ADMIN_USER}', '${ADMIN_HASH}', 'admin')
        ON DUPLICATE KEY UPDATE password = VALUES(password);
SQL
    success "Admin user '${ADMIN_USER}' created"

    # ── Apache vhost ─────────────────────────────────────────────────
    info "Configuring Apache virtual host…"
    cat > /etc/apache2/sites-available/precision-erp.conf <<-VHOST
<VirtualHost *:80>
    ServerName $(hostname -f)
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  \${APACHE_LOG_DIR}/precision-erp-error.log
    CustomLog \${APACHE_LOG_DIR}/precision-erp-access.log combined
</VirtualHost>
VHOST
    a2enmod rewrite >> "$LOG_FILE" 2>&1
    a2ensite precision-erp.conf >> "$LOG_FILE" 2>&1
    systemctl reload apache2 >> "$LOG_FILE" 2>&1
    success "Apache vhost enabled"

    # ── Permissions ──────────────────────────────────────────────────
    info "Setting ownership & permissions…"
    chown -R www-data:www-data "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/logs"
    success "Permissions set (owner: www-data)"

    # ── Cron jobs ────────────────────────────────────────────────────
    info "Installing cron jobs…"
    cat > "$CRON_FILE" <<-CRON
# Precision Ink ERP – Scheduled tasks
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

# Send pending notifications every 15 minutes
*/15 * * * * www-data php ${APP_DIR}/cli/notify.php >> ${APP_DIR}/logs/notify.log 2>&1

# Monthly inventory snapshot at 23:59 on the last day of each month
59 23 28-31 * * www-data [ "\$(date -d tomorrow +\%d)" = "01" ] && php ${APP_DIR}/cli/snapshot.php >> ${APP_DIR}/logs/snapshot.log 2>&1

# Daily database backup at 02:00
0 2 * * * www-data php ${APP_DIR}/cli/backup.php >> ${APP_DIR}/logs/backup.log 2>&1
CRON
    chmod 644 "$CRON_FILE"
    success "Cron jobs installed (${CRON_FILE})"

fi

# ═════════════════════════════════════════════════════════════════════
# UPDATE
# ═════════════════════════════════════════════════════════════════════
if [[ "$MODE" == "update" ]]; then

    cd "$APP_DIR"

    info "Pulling latest changes…"
    git pull >> "$LOG_FILE" 2>&1
    success "Repository updated"

    info "Updating Composer dependencies…"
    composer update --no-interaction --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1
    success "Composer dependencies updated"

    # Read DB credentials from existing config
    DB_HOST="$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_HOST'];")"
    DB_NAME="$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_NAME'];")"
    DB_USER="$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_USER'];")"
    DB_PASS="$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_PASS'];")"

    # ── Run unapplied migrations ─────────────────────────────────────
    info "Checking for new migrations…"
    for file in "${APP_DIR}"/migrations/*.sql; do
        [[ -f "$file" ]] || continue
        base="$(basename "$file")"
        already=$(mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" \
            -sse "SELECT COUNT(*) FROM schema_migrations WHERE filename='${base}';")
        if [[ "$already" -eq 0 ]]; then
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" < "$file" 2>> "$LOG_FILE"
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" \
                -e "INSERT INTO schema_migrations (filename) VALUES ('${base}');"
            success "  Applied: ${base}"
        fi
    done
    success "Migrations up to date"

    # ── Permissions ──────────────────────────────────────────────────
    info "Updating permissions…"
    chown -R www-data:www-data "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/logs"
    success "Permissions refreshed"

fi

# ═════════════════════════════════════════════════════════════════════
# Summary
# ═════════════════════════════════════════════════════════════════════
echo ""
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Precision Ink ERP – ${MODE^^} COMPLETE${NC}"
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo -e "  App directory : ${APP_DIR}"
echo -e "  Log file      : ${LOG_FILE}"
if [[ "$MODE" == "install" ]]; then
    echo -e "  URL           : ${APP_URL}"
    echo -e "  Admin user    : ${ADMIN_USER}"
    echo -e "  API key       : ${API_KEY}"
fi
echo -e "${GREEN}════════════════════════════════════════════════════${NC}"
echo ""
