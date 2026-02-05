FROM php:8.4-apache

# 1. Встановлюємо системні пакети та розширення PHP
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring bcmath gd

# 2. Налаштовуємо PHP 8.4, щоб він ігнорував динамічні властивості (Pixie fix)
# Створюємо власний ini файл з правильним рівнем помилок
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" > /usr/local/etc/php/conf.d/render-php.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/render-php.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/render-php.ini

# 3. Встановлюємо Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Умикаємо mod_rewrite для роботи .htaccess
RUN a2enmod rewrite

# 5. Копіюємо всі файли проєкту
COPY . /var/www/html/

# 6. Встановлюємо правильні права доступу
# Це важливо для читання .env та запису логів
RUN chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

# 7. Встановлюємо залежності через Composer
# Додано прапорець --no-interaction для автоматизації на Render
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-interaction

# 8. Налаштовуємо Apache на папку public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Дозволяємо .htaccess перевизначати правила
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

EXPOSE 80
