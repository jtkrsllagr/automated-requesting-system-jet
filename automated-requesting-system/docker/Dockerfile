FROM php:8.2-apache

# ── System deps ── //
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
        ghostscript \
    && docker-php-ext-install pdo pdo_mysql gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ── Apache config ── //
RUN a2enmod rewrite ssl

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite 000-default default-ssl

# ── Shared HTTPS certificate volume ── //
RUN mkdir -p /etc/ars-certs
COPY docker/entrypoint.sh /usr/local/bin/ars-entrypoint
RUN chmod +x /usr/local/bin/ars-entrypoint
ENTRYPOINT ["/usr/local/bin/ars-entrypoint"]

# ── PHP upload limits ── //
# Forms allow supporting-document attachments up to 20MB each, multiple per
# submission. PHP's stock defaults (upload_max_filesize=2M, post_max_size=8M)
# would silently reject/truncate these before the app's own validation even
# runs, regardless of what the app-level checks allow.
RUN { \
        echo 'upload_max_filesize = 100M'; \
        echo 'post_max_size = 110M'; \
    } > /usr/local/etc/php/conf.d/ars-uploads.ini

# ── Composer ── //
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── App files ── //
WORKDIR /var/www/automated-requesting-system

# Copy project files into the image.
# Because COPY runs inside the Linux container, only app/Helpers/ (capital H)
# from the Windows host is copied — app/helpers/ (lowercase) does not exist
# separately on a case-insensitive Windows filesystem, so there is no duplicate.
COPY . .

# PSR-4 resolves App\Helpers\Csrf → Csrf.php (capital C).
RUN [ -f app/Helpers/csrf.php ] && [ ! -f app/Helpers/Csrf.php ] \
    && mv app/Helpers/csrf.php app/Helpers/Csrf.php || true

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Uploads and storage directories must be writable by www-data
RUN mkdir -p public/uploads storage/approvals storage/avatars \
    && chown -R www-data:www-data public/uploads storage \
    && chmod -R 775 public/uploads storage

EXPOSE 80 443