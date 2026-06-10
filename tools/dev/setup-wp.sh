#!/usr/bin/env bash
# Stand up a throwaway WordPress (SQLite) with this plugin active, for visual/behavioral
# testing in the Claude Code web container. Idempotent-ish; wipes /tmp/wpsite each run.
#
#   bash tools/dev/setup-wp.sh
#   php -S localhost:8765 -t /tmp/wpsite        # then drive with tools/dev/shoot.mjs
#
# Notes: wordpress.org is network-blocked in this environment, so WordPress core, wp-cli,
# and the SQLite drop-in are pulled from GitHub. Admin login is admin / admin.
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SITE=/tmp/wpsite
DL=/tmp/dl
WP="php $DL/wp-cli.phar --path=$SITE --allow-root"
UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
WP_VER="6.7.1"

mkdir -p "$DL"
[ -f "$DL/wp.tar.gz" ]   || curl -sSL -o "$DL/wp.tar.gz"   "https://github.com/WordPress/WordPress/archive/refs/tags/${WP_VER}.tar.gz"
[ -f "$DL/wp-cli.phar" ] || curl -sSL -o "$DL/wp-cli.phar" "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"
[ -f "$DL/sqlite.zip" ]  || curl -sSL -o "$DL/sqlite.zip"  "https://github.com/WordPress/sqlite-database-integration/archive/refs/heads/main.zip"

rm -rf "$SITE"
mkdir -p /tmp/extract && rm -rf /tmp/extract/*
tar -xzf "$DL/wp.tar.gz" -C /tmp/extract
mv "/tmp/extract/WordPress-${WP_VER}" "$SITE"
( cd "$DL" && unzip -oq sqlite.zip )

# SQLite drop-in
cp -a "$DL/sqlite-database-integration-main" "$SITE/wp-content/plugins/sqlite-database-integration"
IMPL="$SITE/wp-content/plugins/sqlite-database-integration"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#${IMPL}#g" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
    "$DL/sqlite-database-integration-main/db.copy" > "$SITE/wp-content/db.php"

cat > "$SITE/wp-config.php" <<'PHP'
<?php
define( 'DB_NAME', 'wordpress' ); define( 'DB_USER', 'root' ); define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' ); define( 'DB_CHARSET', 'utf8' ); define( 'DB_COLLATE', '' );
$table_prefix = 'wp_';
define( 'WP_DEBUG', true ); define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', '/tmp/wpsite/wp-content/debug.log' );
define( 'WP_HOME', 'http://localhost:8765' ); define( 'WP_SITEURL', 'http://localhost:8765' );
define( 'AUTOMATIC_UPDATER_DISABLED', true ); define( 'FS_METHOD', 'direct' );
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );
require_once ABSPATH . 'wp-settings.php';
PHP

ln -sfn "$REPO" "$SITE/wp-content/plugins/equinenetwork-gam"

$WP core install --url="http://localhost:8765" --title="EN GAM Dev" \
    --admin_user=admin --admin_password=admin --admin_email=dev@example.com --skip-email
$WP plugin activate equinenetwork-gam
$WP eval-file "$REPO/tools/dev/seed.php"

echo "Done. Run:  php -S localhost:8765 -t $SITE"
echo "Admin: http://localhost:8765/wp-admin/  (admin / admin)"
