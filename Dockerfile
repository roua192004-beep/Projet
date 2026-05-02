FROM php:8.2-apache

# Installer les dépendances systèmes nécessaires
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Installer les extensions PHP nécessaires (PDO MySQL et Zip pour PhpSpreadsheet)
RUN docker-php-ext-install pdo pdo_mysql zip

# Activer le module Apache rewrite
RUN a2enmod rewrite

# Installer Composer globalement
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier le code de l'application
COPY . /var/www/html/

# Configurer Apache pour écouter sur le port dynamique fourni par Render ($PORT)
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Définir le répertoire de travail
WORKDIR /var/www/html/

# Installer les dépendances PHP via Composer
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Assurer que www-data possède les droits sur les fichiers (important pour les logs ou exports)
RUN chown -R www-data:www-data /var/www/html

EXPOSE ${PORT}
