# -------------------------------------------------------
# Finpay Technologies - Dockerfile
# PHP 8.2 Apache - Single container serving PHP app + assets
# -------------------------------------------------------

# ── Stage 1: Build (install dependencies) ────────────
FROM php:8.2-apache AS builder

# Install system dependencies
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

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies (production - no dev dependencies)
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader

# ── Stage 2: Production image ─────────────────────────
FROM php:8.2-apache AS production

# Install runtime PHP extensions
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

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite headers

# Apache configuration
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
ServerTokens Prod\n\
ServerSignature Off' > /etc/apache2/conf-available/finpay.conf \
    && a2enconf finpay

# Set working directory
WORKDIR /var/www/html

# Copy vendor from builder stage
COPY --from=builder /var/www/html/vendor ./vendor

# Copy application source code
COPY auth/ ./auth/
COPY includes/ ./includes/
COPY user/ ./user/
COPY assets/ ./assets/
COPY .gitignore ./
COPY composer.json ./

# Copy architecture.html if present
COPY architecture.html ./

# Set correct file permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Remove vendor dev files not needed at runtime
RUN rm -rf vendor/phpunit

# Health check endpoint
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health.php || exit 1

# Expose port 80
EXPOSE 80

# Apache runs as foreground process
CMD ["apache2-foreground"]
