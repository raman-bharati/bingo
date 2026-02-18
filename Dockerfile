FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libicu-dev \
    libpq-dev \
    git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl && docker-php-ext-install intl pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
