# -------------------------------------------------------
# Finpay Technologies - Dockerfile
# PHP 8.2 Apache - Single container serving PHP app + assets
# -------------------------------------------------------

# Stage 1: Build dependencies
FROM php:8.2-apache AS builder

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# Stage 2: Production image
FROM php:8.2-apache AS production

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    curl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    bcmath \
    gd \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
ServerTokens Prod\n\
ServerSignature Off' > /etc/apache2/conf-available/finpay.conf \
    && a2enconf finpay

WORKDIR /var/www/html

COPY --from=builder /var/www/html/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

RUN rm -rf vendor/phpunit || true

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
