# Build stage
FROM php:8.2-cli AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    && docker-php-ext-install intl pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev && \
    rm -rf vendor/.git vendor/*/.git vendor/*/*/.git vendor/*/*/*/.git

# Runtime stage
FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu72 \
    && docker-php-ext-install intl pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /app

COPY --from=builder /app /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
