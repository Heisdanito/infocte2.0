FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli

# Fix MPM conflict - disable event, enable prefork
RUN a2dismod mpm_event && a2enmod mpm_prefork

# Make Apache listen on Railway's dynamic $PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf && \
    sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-enabled/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
