FROM php:8.5-apache

ARG UID=1000
ARG GID=1000

# Dependencias del sistema y extensiones de PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates gnupg \
        libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl gd bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 LTS (Vite / npm)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: DocumentRoot en /public y mod_rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && a2enmod rewrite

# www-data con el mismo UID/GID que el usuario del host (permisos del bind mount)
RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /var/www/.npm /var/www/.composer \
    && chown -R www-data:www-data /var/www

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80 5173

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
