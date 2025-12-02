# FINAL WORKING DOCKERFILE – PHP + NGINX + RENDER DISK (Dec 2025)
FROM php:8.3-fpm

# Install nginx + extensions
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

# Copy app
COPY . /var/www/html
WORKDIR /var/www/html

# Nginx config with direct alias to persistent disk
COPY --chown=www-data:www-data <<-EOF /etc/nginx/sites-available/default
server {
    listen 8080;
    index index.php;
    root /var/www/html;
    server_name _;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
    }

    # Serve uploaded images directly from persistent disk
    location ^~ /public/webapi/uploads/ {
        alias /opt/render/project/src/webapi/uploads/;
        autoindex off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }
}
EOF

# Permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 8080

# Start PHP-FPM in foreground + nginx
CMD php-fpm -D && nginx -g 'daemon off;'