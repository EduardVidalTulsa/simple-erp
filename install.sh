#!/bin/sh
# SPDX-License-Identifier: GPL-3.0-or-later
#
# Installs simple-erp on Debian/Ubuntu without Docker: nginx + PHP-FPM + MariaDB, LaTeX and
# inkscape. Run it as root from the repository directory. Installs into /srv/simple-erp.
#   sudo ./install.sh [domain]        (default domain: _ , any host name)
set -e
DEST=/srv/simple-erp
DOMAIN=${1:-_}
DB=simple_erp
[ "$(id -u)" = 0 ] || { echo "run it as root"; exit 1; }

apt-get update
apt-get install -y --no-install-recommends nginx mariadb-server php-fpm php-mysql php-zip php-gd \
    php-mbstring php-xml php-intl php-curl composer texinfo texlive-latex-base texlive-latex-recommended \
    texlive-latex-extra texlive-fonts-recommended texlive-lang-spanish texlive-xetex inkscape \
    poppler-utils cups-client openssh-client curl

mkdir -p "$DEST"
cp -r web keys.example.php composer.json db "$DEST/"
cd "$DEST/web" && cp ../composer.json . && composer install --no-dev --no-interaction && cd "$DEST"
mkdir -p web/scale/pdf web/pdf
chown -R www-data:www-data web/scale web/pdf

if [ ! -f keys.php ]; then
    PASS=$(head -c 18 /dev/urandom | base64 | tr -d '/+=')
    mariadb -e "CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4;
                CREATE USER IF NOT EXISTS '$DB'@'localhost' IDENTIFIED BY '$PASS';
                GRANT ALL ON $DB.* TO '$DB'@'localhost';"
    mariadb "$DB" < db/schema.sql
    mariadb "$DB" < db/demo.sql
    sed -e "s/^\$mypassword = .*/\$mypassword = '$PASS';/" keys.example.php > keys.php
    chown root:www-data keys.php; chmod 640 keys.php
    echo "database created; configuration in $DEST/keys.php"
fi

PHPSOCK=$(ls /run/php/php*-fpm.sock | head -1)
cat > /etc/nginx/sites-available/simple-erp <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $DEST/web;
    index index.html index.php;
    client_max_body_size 20m;
    location ~ /(vendor|composer\.(json|lock))  { deny all; }
    location ~ \.(lp|tex|log|aux|ps)\$          { deny all; }
    location / { try_files \$uri \$uri/ =404; }
    location ~ \.php\$ {
        try_files \$uri =404;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHPSOCK;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/simple-erp /etc/nginx/sites-enabled/simple-erp
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# background jobs of the scale (labels, PDFs, notifications)
cat > /etc/systemd/system/simple-erp-worker.service <<UNIT
[Unit]
Description=simple-erp background jobs (scale/p.php)
After=nginx.service

[Service]
ExecStart=/bin/sh -c 'while true; do curl -s -o /dev/null "http://localhost/scale/p.php?ID=1"; sleep 10; done'
Restart=always

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload && systemctl enable --now simple-erp-worker

echo "done: open http://$( [ "$DOMAIN" = _ ] && hostname -I | cut -d' ' -f1 || echo "$DOMAIN" )/ (login admin / admin, change it)"
