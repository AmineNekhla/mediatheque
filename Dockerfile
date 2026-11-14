FROM php:8.2-apache

# Extensions PHP nécessaires à Symfony et Doctrine
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip opcache \
 && rm -rf /var/lib/apt/lists/*

# Apache : racine web = public/, toutes les URLs inconnues passent par index.php
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# 1) Dépendances d'abord : cette couche reste en cache tant que composer.lock ne change pas
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# 2) Code de l'application, puis scripts Symfony (cache, assets)
COPY . .
RUN composer dump-autoload --optimize \
 && composer run-script auto-scripts \
 && chown -R www-data:www-data var

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
# Retire d'éventuels retours chariot Windows (CRLF) qui empêcheraient le script de démarrer
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
