#!/bin/sh

# Fix persistent disk permissions
chown -R www-data:www-data /data/uploads
chmod -R 775 /data/uploads

# Start services
php-fpm -D
nginx -g "daemon off;"
