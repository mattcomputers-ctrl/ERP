#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════
#  Precision Ink ERP — Automated Installer & Updater
#
#  Usage (fresh install):
#    curl -fsSL https://raw.githubusercontent.com/mattcomputers-ctrl/ERP/claude/custom-fields-infrastructure-FwaSQ/install.sh | sudo bash
#
#  Usage (update existing):
#    cd /var/www/precision-erp && sudo ./install.sh
#
#  Supports: Ubuntu 22.04, 24.04 (x86_64)
# ═══════════════════════════════════════════════════════════════════════
set -euo pipefail

# ── Branding & Colors ────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

REPO_URL="https://github.com/mattcomputers-ctrl/ERP.git"
REPO_BRANCH="claude/custom-fields-infrastructure-FwaSQ"
APP_DIR="/var/www/precision-erp"

banner() {
    echo ""
    echo -e "${CYAN}${BOLD}"
    echo "  ╔══════════════════════════════════════════════════╗"
    echo "  ║        Precision Ink ERP — Installer v1.0       ║"
    echo "  ╚══════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

info()    { echo -e "  ${CYAN}[INFO]${NC}  $*"; }
ok()      { echo -e "  ${GREEN}[ OK ]${NC}  $*"; }
warn()    { echo -e "  ${YELLOW}[WARN]${NC}  $*"; }
fail()    { echo -e "  ${RED}[FAIL]${NC}  $*"; exit 1; }
step()    { echo ""; echo -e "  ${BOLD}── $* ──${NC}"; }

# ── Pre-flight checks ────────────────────────────────────────────────
banner

[[ $EUID -ne 0 ]] && fail "This script must be run as root. Use: sudo bash install.sh"

OS_ID=$(. /etc/os-release 2>/dev/null && echo "$ID" || echo "unknown")
OS_VER=$(. /etc/os-release 2>/dev/null && echo "$VERSION_ID" || echo "0")
[[ "$OS_ID" != "ubuntu" ]] && warn "This installer is tested on Ubuntu. Your OS: $OS_ID $OS_VER"

# ── Detect install vs update mode ────────────────────────────────────
if [[ -f "${APP_DIR}/config/config.php" && -f "${APP_DIR}/public/index.php" ]]; then
    MODE="update"
    info "Existing installation detected at ${APP_DIR}"
    info "Running in UPDATE mode"
else
    MODE="install"
    info "No existing installation found"
    info "Running in FRESH INSTALL mode"
fi

# ═══════════════════════════════════════════════════════════════════════
#  FRESH INSTALL
# ═══════════════════════════════════════════════════════════════════════
if [[ "$MODE" == "install" ]]; then

    # ── Step 1: System packages ──────────────────────────────────────
    step "Installing system packages"

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq

    # Install prerequisites
    info "Installing prerequisites..."
    apt-get install -y software-properties-common ca-certificates curl git unzip 2>&1 | tail -1

    # Detect PHP version available in default repos first
    PHP_VER=""
    for v in 8.3 8.2 8.4 8.1; do
        if apt-cache show "php${v}-cli" >/dev/null 2>&1; then
            PHP_VER="$v"
            info "Found PHP ${v} in default repositories"
            break
        fi
    done

    # If no PHP found in default repos, add ondrej/php PPA and retry
    if [[ -z "$PHP_VER" ]]; then
        info "No PHP 8.x found in default repos — adding ondrej/php PPA..."
        add-apt-repository -y ppa:ondrej/php 2>&1 | tail -3 || true
        apt-get update -qq
        for v in 8.3 8.2 8.4 8.1; do
            if apt-cache show "php${v}-cli" >/dev/null 2>&1; then
                PHP_VER="$v"
                break
            fi
        done
    fi

    if [[ -z "$PHP_VER" ]]; then
        fail "Could not find any PHP 8.x package. Check your package sources."
    fi
    info "Using PHP ${PHP_VER}"

    apt-get install -y \
        apache2 \
        mysql-server \
        "php${PHP_VER}" \
        "libapache2-mod-php${PHP_VER}" \
        "php${PHP_VER}-mysql" \
        "php${PHP_VER}-mbstring" \
        "php${PHP_VER}-xml" \
        "php${PHP_VER}-curl" \
        "php${PHP_VER}-zip" \
        "php${PHP_VER}-gd" \
        "php${PHP_VER}-intl" \
        "php${PHP_VER}-bcmath" \
        unzip 2>&1 | tail -5
    ok "Apache, MySQL, PHP ${PHP_VER} installed"

    # Start and enable services
    systemctl enable --now apache2 mysql
    ok "Services enabled"

    # ── Step 2: Composer ─────────────────────────────────────────────
    step "Installing Composer"
    if ! command -v composer &>/dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet
        ok "Composer installed"
    else
        ok "Composer already installed"
    fi

    # ── Step 3: Clone repository ─────────────────────────────────────
    step "Downloading Precision Ink ERP"
    if [[ -d "${APP_DIR}/.git" ]]; then
        info "Repository already exists, pulling latest..."
        cd "$APP_DIR"
        git fetch origin && git checkout "$REPO_BRANCH" && git pull origin "$REPO_BRANCH"
    else
        mkdir -p "$(dirname "$APP_DIR")"
        git clone -b "$REPO_BRANCH" "$REPO_URL" "$APP_DIR"
        cd "$APP_DIR"
    fi
    ok "Source code downloaded to ${APP_DIR}"

    # ── Step 4: PHP dependencies ─────────────────────────────────────
    step "Installing PHP dependencies"
    cd "$APP_DIR"
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader --quiet
    ok "Dependencies installed"

    # ── Step 5: Storage directories ──────────────────────────────────
    step "Creating storage directories"
    mkdir -p storage/attachments storage/backups logs
    ok "Directories created"

    # ── Step 6: Database setup ───────────────────────────────────────
    step "Database configuration"
    echo ""
    read -rp "    Database name [precision_erp]: " DB_NAME < /dev/tty
    DB_NAME="${DB_NAME:-precision_erp}"
    read -rp "    Database user [precisionink]: " DB_USER < /dev/tty
    DB_USER="${DB_USER:-precisionink}"

    # Generate a random password or let user choose
    GENERATED_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
    read -rp "    Database password [auto-generated]: " DB_PASS < /dev/tty
    DB_PASS="${DB_PASS:-$GENERATED_PASS}"

    DB_HOST="localhost"

    info "Creating database and user..."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "ALTER USER '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}'; FLUSH PRIVILEGES;"
    ok "Database '${DB_NAME}' ready"

    # ── Step 7: Run migrations ───────────────────────────────────────
    step "Running database migrations"

    # Do NOT pre-create schema_migrations — migration 0001 creates it.
    # Just run each .sql file in order. The files themselves track
    # their own application via INSERT INTO schema_migrations.

    MIGRATION_COUNT=0
    for file in "${APP_DIR}"/migrations/*.sql; do
        [[ -f "$file" ]] || continue
        base="$(basename "$file")"

        # Check if schema_migrations table exists yet (created by 0001)
        TABLE_EXISTS=$(mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -sse \
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='schema_migrations';" 2>/dev/null || echo "0")

        if [[ "$TABLE_EXISTS" -gt 0 ]]; then
            # Table exists — check if this migration was already applied
            already=$(mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -sse \
                "SELECT COUNT(*) FROM schema_migrations WHERE migration_name LIKE '%${base%.*}%';" 2>/dev/null || echo "0")
            if [[ "$already" -gt 0 ]]; then
                continue  # Skip — already applied
            fi
        fi

        # Run the migration with --force to continue past individual statement errors
        # (e.g. ALTER TABLE ADD COLUMN that already exists)
        info "  Running: ${base}"
        mysql --force -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "$file" 2>/dev/null || true
        ok "  Applied: ${base}"
        MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
    done
    ok "${MIGRATION_COUNT} migration(s) processed"

    # ── Step 8: Generate config ──────────────────────────────────────
    step "Generating configuration"

    SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "localhost")

    cat > "${APP_DIR}/config/config.php" <<PHPCONFIG
<?php
return [
    'DB_HOST' => '${DB_HOST}',
    'DB_NAME' => '${DB_NAME}',
    'DB_USER' => '${DB_USER}',
    'DB_PASS' => '${DB_PASS}',
    'APP_URL' => 'http://${SERVER_IP}',
    'APP_NAME' => 'Precision Ink ERP',
];
PHPCONFIG
    ok "Config file created"

    # ── Step 9: Create admin user ────────────────────────────────────
    step "Creating admin user"
    echo ""
    read -rp "    Admin username [admin]: " ADMIN_USER < /dev/tty
    ADMIN_USER="${ADMIN_USER:-admin}"
    read -rp "    Admin full name [System Administrator]: " ADMIN_NAME < /dev/tty
    ADMIN_NAME="${ADMIN_NAME:-System Administrator}"
    read -rp "    Admin email [admin@localhost]: " ADMIN_EMAIL < /dev/tty
    ADMIN_EMAIL="${ADMIN_EMAIL:-admin@localhost}"
    read -rsp "    Admin password [password]: " ADMIN_PASS < /dev/tty; echo ""
    ADMIN_PASS="${ADMIN_PASS:-password}"

    ADMIN_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")

    mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "
        INSERT INTO \`groups\` (name, is_system_admin, active)
        SELECT 'System Administrators', 1, 1
        FROM dual WHERE NOT EXISTS (SELECT 1 FROM \`groups\` WHERE is_system_admin = 1);
    " 2>/dev/null || true

    GROUP_ID=$(mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -sse \
        "SELECT id FROM \`groups\` WHERE is_system_admin = 1 LIMIT 1;" 2>/dev/null || echo "1")

    mysql --force -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "
        INSERT IGNORE INTO users (username, password_hash, full_name, email, group_id, active)
        VALUES ('${ADMIN_USER}', '${ADMIN_HASH}', '${ADMIN_NAME}', '${ADMIN_EMAIL}', ${GROUP_ID}, 1);
    " 2>/dev/null || true
    ok "Admin user '${ADMIN_USER}' created"

    # ── Step 10: System settings ─────────────────────────────────────
    step "Setting initial system configuration"
    mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "
        INSERT INTO system_settings (setting_key, setting_value) VALUES
            ('company_name', 'Precision Ink LLC'),
            ('company_address', ''),
            ('company_phone', '')
        ON DUPLICATE KEY UPDATE setting_value = setting_value;
    " 2>/dev/null || true
    ok "System settings initialized"

    # ── Step 11: Apache configuration ────────────────────────────────
    step "Configuring Apache web server"

    # Create .htaccess
    cat > "${APP_DIR}/public/.htaccess" <<'HTACCESS'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
HTACCESS

    # Create vhost
    cat > /etc/apache2/sites-available/precision-erp.conf <<VHOST
<VirtualHost *:80>
    ServerName ${SERVER_IP}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <Directory ${APP_DIR}>
        Require all denied
    </Directory>
    <Directory ${APP_DIR}/public>
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/precision-erp-error.log
    CustomLog \${APACHE_LOG_DIR}/precision-erp-access.log combined
</VirtualHost>
VHOST

    a2enmod rewrite >/dev/null 2>&1
    a2dissite 000-default.conf >/dev/null 2>&1 || true
    a2ensite precision-erp.conf >/dev/null 2>&1

    # PHP settings
    PHP_INI="/etc/php/${PHP_VER}/apache2/php.ini"
    if [[ -f "$PHP_INI" ]]; then
        sed -i 's/^upload_max_filesize.*/upload_max_filesize = 50M/' "$PHP_INI"
        sed -i 's/^post_max_size.*/post_max_size = 50M/' "$PHP_INI"
        sed -i 's/^memory_limit.*/memory_limit = 256M/' "$PHP_INI"
        sed -i 's/^max_execution_time.*/max_execution_time = 120/' "$PHP_INI"
    fi

    systemctl restart apache2
    ok "Apache configured and restarted"

    # ── Step 12: File permissions ────────────────────────────────────
    step "Setting file permissions"
    chown -R www-data:www-data "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/logs"
    chmod +x "${APP_DIR}/install.sh"
    ok "Permissions set"

    # ── Step 13: Cron job ────────────────────────────────────────────
    step "Installing cron job"
    CRON_LINE="*/15 * * * * www-data /usr/bin/php ${APP_DIR}/cli/notify.php >> ${APP_DIR}/logs/cron.log 2>&1"
    echo "$CRON_LINE" > /etc/cron.d/precision-erp
    chmod 644 /etc/cron.d/precision-erp
    ok "Cron job installed (every 15 minutes)"

fi

# ═══════════════════════════════════════════════════════════════════════
#  UPDATE MODE
# ═══════════════════════════════════════════════════════════════════════
if [[ "$MODE" == "update" ]]; then

    step "Updating Precision Ink ERP"
    cd "$APP_DIR"

    info "Pulling latest code..."
    git pull origin "$REPO_BRANCH" 2>/dev/null || git fetch origin && git checkout "$REPO_BRANCH" && git pull
    ok "Code updated"

    info "Updating dependencies..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader --quiet
    ok "Dependencies updated"

    # Read DB creds from config
    DB_HOST=$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_HOST'];")
    DB_NAME=$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_NAME'];")
    DB_USER=$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_USER'];")
    DB_PASS=$(php -r "\$c=require '${APP_DIR}/config/config.php'; echo \$c['DB_PASS'];")

    step "Running new migrations"
    MIGRATION_COUNT=0
    for file in "${APP_DIR}"/migrations/*.sql; do
        [[ -f "$file" ]] || continue
        base="$(basename "$file")"
        base_no_ext="${base%.*}"
        # Match with or without .sql extension for backward compatibility
        already=$(mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" -sse \
            "SELECT COUNT(*) FROM schema_migrations WHERE migration_name LIKE '%${base_no_ext}%';" 2>/dev/null || echo "0")
        if [[ "$already" -eq 0 ]]; then
            info "  Running: ${base}"
            mysql --force -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" < "$file" 2>/dev/null || true
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" -e \
                "INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('${base}');" 2>/dev/null || true
            ok "  Applied: ${base}"
            MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
        fi
    done
    ok "${MIGRATION_COUNT} new migration(s) applied"

    # Fix collation mismatches — MySQL 8 may create tables with utf8mb4_0900_ai_ci
    # even when the database default is utf8mb4_unicode_ci
    step "Checking table collations"
    TABLES_TO_FIX=$(mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" -sse \
        "SELECT GROUP_CONCAT(TABLE_NAME) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_COLLATION != 'utf8mb4_unicode_ci'
           AND TABLE_COLLATION IS NOT NULL;" 2>/dev/null || echo "")
    if [[ -n "$TABLES_TO_FIX" && "$TABLES_TO_FIX" != "NULL" ]]; then
        IFS=',' read -ra FIX_ARRAY <<< "$TABLES_TO_FIX"
        for tbl in "${FIX_ARRAY[@]}"; do
            mysql -u"${DB_USER}" -p"${DB_PASS}" -h"${DB_HOST}" "${DB_NAME}" -e \
                "ALTER TABLE \`${tbl}\` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || true
        done
        ok "Fixed collation on ${#FIX_ARRAY[@]} table(s)"
    else
        ok "All table collations OK"
    fi

    step "Refreshing permissions"
    chown -R www-data:www-data "$APP_DIR"
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/logs"
    systemctl restart apache2
    ok "Update complete"

fi

# ═══════════════════════════════════════════════════════════════════════
#  DONE
# ═══════════════════════════════════════════════════════════════════════
echo ""
echo -e "${GREEN}${BOLD}"
echo "  ╔══════════════════════════════════════════════════════════╗"
echo "  ║       Precision Ink ERP — ${MODE^^} COMPLETE              ║"
echo "  ╠══════════════════════════════════════════════════════════╣"
if [[ "$MODE" == "install" ]]; then
echo "  ║                                                          ║"
echo "  ║  URL:       http://${SERVER_IP}                          "
echo "  ║  Username:  ${ADMIN_USER}                                "
echo "  ║  Password:  (what you entered during setup)              "
echo "  ║                                                          ║"
echo "  ║  Config:    ${APP_DIR}/config/config.php                 "
echo "  ║  Logs:      ${APP_DIR}/logs/                             "
echo "  ║                                                          ║"
echo "  ║  NEXT STEPS:                                             ║"
echo "  ║   1. Open the URL above in your browser                 ║"
echo "  ║   2. Log in with your admin credentials                 ║"
echo "  ║   3. Go to Settings > Company to configure               ║"
echo "  ║   4. Add your first facility in Settings > Facilities    ║"
echo "  ║   5. Start adding items, customers, and suppliers        ║"
echo "  ║                                                          ║"
echo "  ║  TO UPDATE LATER:                                        ║"
echo "  ║   cd ${APP_DIR} && sudo ./install.sh                    "
fi
echo "  ║                                                          ║"
echo "  ╚══════════════════════════════════════════════════════════╝"
echo -e "${NC}"
