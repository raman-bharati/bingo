FROM php:8.2-cli

# Install required PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev \
    && docker-php-ext-install -j$(nproc) intl pdo pdo_mysql \
    && apt-get remove -y libicu-dev \
    && apt-get autoremove -y \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install dependencies (production only)
RUN composer install --optimize-autoloader --no-scripts --no-interaction --no-dev && \
    rm -rf vendor/.git vendor/*/.git vendor/*/*/.git

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
