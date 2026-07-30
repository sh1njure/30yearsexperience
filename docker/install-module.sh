#!/bin/sh
# Enable the combinationdescriptions module once PrestaShop has finished its
# first-boot auto-installation.
#
# Runs as a one-shot sidecar sharing the /var/www/html volume with the web
# container. It waits for the shop's files to appear and for the core install to
# complete, then keeps retrying `prestashop:module install` until it succeeds
# (the command only works after PrestaShop itself is installed). Idempotent: a
# second run just reports the module is already installed.
set -eu

APP_DIR="/var/www/html"
MODULE="combinationdescriptions"
MAX_ATTEMPTS="${CD_INSTALL_MAX_ATTEMPTS:-60}"   # ~10 min at 10s intervals
SLEEP_SECONDS="${CD_INSTALL_SLEEP:-10}"

cd "$APP_DIR" 2>/dev/null || {
    echo "[cd-installer] $APP_DIR not mounted yet, waiting..."
    until cd "$APP_DIR" 2>/dev/null; do sleep "$SLEEP_SECONDS"; done
}

echo "[cd-installer] Waiting for PrestaShop files (bin/console)..."
until [ -f "$APP_DIR/bin/console" ]; do sleep "$SLEEP_SECONDS"; done

echo "[cd-installer] Waiting for PrestaShop core install (app/config/parameters.php)..."
until [ -f "$APP_DIR/app/config/parameters.php" ]; do sleep "$SLEEP_SECONDS"; done

attempt=1
while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    echo "[cd-installer] Attempt ${attempt}/${MAX_ATTEMPTS}: installing ${MODULE}..."
    if php bin/console prestashop:module install "$MODULE" >/tmp/cd-install.log 2>&1; then
        echo "[cd-installer] Module installed."
        cat /tmp/cd-install.log
        php bin/console cache:clear --no-warmup >/dev/null 2>&1 || true
        chown -R www-data:www-data "$APP_DIR/var" >/dev/null 2>&1 || true
        echo "[cd-installer] Done."
        exit 0
    fi

    # Already installed? Treat as success.
    if grep -qi "already installed" /tmp/cd-install.log; then
        echo "[cd-installer] Module already installed."
        exit 0
    fi

    echo "[cd-installer] PrestaShop not ready yet; retrying in ${SLEEP_SECONDS}s."
    tail -n 3 /tmp/cd-install.log 2>/dev/null || true
    attempt=$((attempt + 1))
    sleep "$SLEEP_SECONDS"
done

echo "[cd-installer] ERROR: could not install ${MODULE} after ${MAX_ATTEMPTS} attempts."
cat /tmp/cd-install.log 2>/dev/null || true
exit 1
