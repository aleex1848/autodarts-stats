#!/bin/sh
cd /app
composer install --no-interaction --no-dev --prefer-dist
#php artisan telescope:install
#php artisan horizon:publish
php artisan migrate --force
php artisan optimize:clear

#config:clear damit env() im code wieder funktioniert.
#php artisan config:clear
touch /app/storage/logs/laravel.log
chmod 666 /app/storage/logs/laravel.log
#cp -f composer.lock /app/storage/app/non-public/composer.lock
npm install
npm run build --production
php artisan optimize
chown -R 1000:1000 /app/storage