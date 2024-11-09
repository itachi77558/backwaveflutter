# Utilisation de l'image PHP officielle
FROM php:8.1-fpm

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \   
    && pecl install grpc \
    && docker-php-ext-enable grpc

# Installation des extensions PHP requises  
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install pdo_mysql pdo_pgsql gd mbstring zip xml  # Ajout de pdo_pgsql pour PostgreSQL

# Installation de Composer   
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet dans le conteneur
COPY . .

# Installation des dépendances PHP avec Composer
RUN composer install --no-dev --optimize-autoloader

# Ajuster les permissions des fichiers
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Exposer le port défini dans la variable d'environnement PORT
EXPOSE $PORT

# Commande pour démarrer l'application
CMD php artisan serve --host=0.0.0.0 --port=$PORT