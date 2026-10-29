#!/bin/sh
# SPDX-License-Identifier: GPL-3.0-or-later
# Creates the configuration (volume /var/www/conf/keys.php, linked as /var/www/keys.php) from
# keys.example.php and the environment the first time; later edits to it are kept.
set -e
if [ ! -f /var/www/conf/keys.php ]; then
    sed -e "s/^\$myserver = .*/\$myserver = '${DB_HOST:-db}';/" \
        -e "s/^\$myuser = .*/\$myuser = '${DB_USER:-simple_erp}';/" \
        -e "s/^\$mypassword = .*/\$mypassword = '${DB_PASSWORD:-simple_erp}';/" \
        -e "s/^\$mydatabase = .*/\$mydatabase = '${DB_NAME:-simple_erp}';/" \
        -e "s/^\$empresa_nom = .*/\$empresa_nom = '${SHOP_NAME:-Example Shop}';/" \
        /var/www/keys.example.php > /var/www/conf/keys.php
    echo "simple-erp: created conf/keys.php, edit it for printers, Telegram, e-mail..."
fi
ln -sf /var/www/conf/keys.php /var/www/keys.php
echo "date.timezone=${TZ:-Europe/Madrid}" > /usr/local/etc/php/conf.d/timezone.ini
exec docker-php-entrypoint "$@"
