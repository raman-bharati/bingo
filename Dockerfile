FROM php:8.2-cli

# Install required extensions
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    git \
    curl \
    && docker-php-ext-install intl pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-scripts --no-interaction

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
