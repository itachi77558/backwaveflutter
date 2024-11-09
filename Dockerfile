# Étape 1 : utiliser une image de base officielle PHP avec extensions nécessaires pour Laravel et PostgreSQL
FROM php:8.1-fpm

# Installer des dépendances supplémentaires, PostgreSQL et outils nécessaires
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_pgsql

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet dans le conteneur
COPY . .

# Installer les dépendances du projet avec Composer
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Générer la clé d'application Laravel
RUN php artisan key:generate

# Donner les permissions nécessaires au dossier de stockage
RUN chmod -R 777 storage bootstrap/cache

# Exposer le port 9000 pour PHP-FPM
EXPOSE 9000

# Exécuter les migrations et le serveur
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=9000
