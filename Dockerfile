FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libicu-dev \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev -d /var/www/html

RUN chown -R www-data:www-data /var/www/html

ENV PORT=10000

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && apache2ctl -D FOREGROUND"]