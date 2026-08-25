# PHP front-end for the Student Health Record System.
# Serves ONLY the public/ directory (document root); app/, database/,
# tests/ and ai-service/ are not web-accessible.
FROM php:8.3-apache

# Runtime PHP extensions required by the application:
#   pdo_mysql - all database access is PDO
#   curl      - AiClient (HTTP to the FastAPI service)
#   mbstring  - mb_substr() / mb_strlen() used throughout
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && a2dismod mpm_event \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Point the Apache document root at public/ and allow .htaccess overrides.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory ${APACHE_DOCUMENT_ROOT}>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/docroot.conf \
    && a2enconf docroot

WORKDIR /var/www/html

# Runtime configuration comes from the environment (DB_*, AI_*, APP_*).
# Real environment variables always win over a .env file (see
# app/Core/Environment.php), so no .env is baked into the image.
COPY --chown=www-data:www-data . /var/www/html/

# Writable log directory.
RUN mkdir -p /var/www/html/app/Logs \
    && chown -R www-data:www-data /var/www/html/app

# Bind Apache to Railway's PORT (defaults to 80 for local/compose runs).
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80