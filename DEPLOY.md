# Precision Ink ERP – Deployment Guide

## Prerequisites

- Ubuntu Server 22.04 LTS
- Root / sudo access
- Internet connectivity (for apt & Composer)
- Git installed (`sudo apt install git` if missing)

## Clone

```bash
cd /var/www
sudo git clone https://github.com/mattcomputers-ctrl/erp.git precision-erp
cd precision-erp
```

## Install

```bash
sudo chmod +x install.sh
sudo ./install.sh
```

The installer will:

1. Install Apache, MySQL 8.0, PHP 8.2 and required extensions
2. Install Composer and download dependencies
3. Prompt for database credentials and create the DB + user
4. Run all migrations
5. Generate `config/config.php` from the template
6. Create an admin user
7. Configure the Apache virtual host and enable `mod_rewrite`
8. Set file ownership to `www-data`
9. Install cron jobs for notifications, snapshots, and backups

## Update

```bash
cd /var/www/precision-erp
sudo ./install.sh
```

When an existing `config/config.php` is detected the script runs in update mode:

- Pulls the latest code from Git
- Runs `composer update`
- Applies any new migrations (tracked in `schema_migrations`)
- Refreshes file permissions

## Useful Commands

### Tail application logs

```bash
tail -f /var/www/precision-erp/logs/*.log
```

### Tail Apache logs

```bash
tail -f /var/log/apache2/precision-erp-*.log
```

### Manual database backup

```bash
sudo -u www-data php /var/www/precision-erp/cli/backup.php
```

### Restore a database backup

```bash
mysql -u erp_user -p precision_erp < /var/www/precision-erp/storage/backups/BACKUP_FILE.sql
```

### Run notifications manually

```bash
sudo -u www-data php /var/www/precision-erp/cli/notify.php
```
