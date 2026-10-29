# SPDX-License-Identifier: GPL-3.0-or-later
FROM php:8.3-fpm-bookworm

# LaTeX for the PDF documents, inkscape for the labels, ssh/scp for remote printers
RUN apt-get update && apt-get install -y --no-install-recommends \
        texinfo texlive-latex-base texlive-latex-recommended texlive-latex-extra \
        texlive-fonts-recommended texlive-lang-spanish texlive-xetex \
        inkscape cups-client openssh-client curl unzip libzip-dev libpng-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype \
    && docker-php-ext-install -j"$(nproc)" mysqli zip gd intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY composer.json /var/www/web/composer.json
RUN cd /var/www/web && composer install --no-dev --no-interaction --no-progress

COPY web/ /var/www/web/
COPY keys.example.php /var/www/keys.example.php
COPY docker/entrypoint.sh /usr/local/bin/simple-erp-entrypoint
RUN mkdir -p /var/www/web/scale/pdf /var/www/web/pdf \
    && chown -R www-data:www-data /var/www/web/scale /var/www/web/pdf

ENTRYPOINT ["simple-erp-entrypoint"]
CMD ["php-fpm"]
