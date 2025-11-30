FROM php:8.2-apache
COPY . /var/www/html/
RUN a2enmod rewrite
EXPOSE 8080
CMD ["apache2-foreground"]