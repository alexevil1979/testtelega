# TestTelega — Docker image
FROM php:8.2-apache

# Системные зависимости для MadelineProto
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libgmp-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql zip xml gmp gd mbstring bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/testtelega

# Копируем проект
COPY . .

# Установка зависимостей
RUN composer install --no-dev --optimize-autoloader

# Apache: DocumentRoot = public/
ENV APACHE_DOCUMENT_ROOT=/var/www/testtelega/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Права
RUN mkdir -p sessions logs \
    && chown -R www-data:www-data sessions logs \
    && chmod 750 sessions

EXPOSE 80

CMD ["apache2-foreground"]
