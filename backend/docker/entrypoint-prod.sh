#!/bin/sh
set -e

# Permissions runtime (volume monté avec l'ownership de l'hôte possible).
mkdir -p var/cache var/log /run/nginx
chown -R www-data:www-data var /run/nginx

# Symfony exige un fichier .env au boot (même vide) ; les vraies valeurs
# (APP_ENV, APP_SECRET, DATABASE_URL, JWT_*) viennent des variables
# d'environnement du conteneur, jamais d'un .env versionné.
[ -f /var/www/html/.env ] || touch /var/www/html/.env

# Clés JWT : jamais versionnées — générées au premier démarrage si absentes.
if [ ! -f /var/www/html/config/jwt/private.pem ] && [ -n "$JWT_PASSPHRASE" ]; then
    php /var/www/html/bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

# La commande ci-dessus (exécutée en root) boote le kernel Symfony et crée
# var/cache/prod appartenant à root. On re-prend possession de var pour que
# les workers php-fpm (www-data) puissent écrire le cache à l'exécution.
chown -R www-data:www-data var /run/nginx

php-fpm -D
exec nginx -g 'daemon off;'
