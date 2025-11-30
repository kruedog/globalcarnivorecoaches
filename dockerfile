FROM php:8.3-apache

# Enable Apache mod_rewrite (needed for pretty URLs and PHP execution)
RUN a2enmod rewrite

# Copy your site files
COPY . /var/www/html/

# Make sure .php files are executable and webapi folder is writable (for future uploads)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/webapi/uploads 2>/dev/null || true

# Critical: Tell Apache the document root is correct and allow .htaccess overrides
RUN echo "<Directory /var/www/html>" > /etc/apache2/sites-available/000-default.conf && \
    echo "    Options Indexes FollowSymLinks" >> /etc/apache2/sites-available/000-default.conf && \
    echo "    AllowOverride All" >> /etc/apache2/sites-available/000-default.conf && \
    echo "    Require all granted" >> /etc/apache2/sites-available/000-default.conf && \
    echo "</Directory>" >> /etc/apache2/sites-available/000-default.conf

EXPOSE 8080
CMD ["apache2-foreground"]