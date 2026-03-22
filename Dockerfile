FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli

# Make Apache listen on Railway's dynamic $PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf && \
    sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-enabled/000-default.conf

ENV APACHE_ENVVARS /etc/apache2/envvars

EXPOSE ${PORT}

CMD ["apache2-foreground"]
