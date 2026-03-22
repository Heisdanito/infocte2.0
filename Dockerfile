FROM php:8.2-cli

COPY . /app/

RUN docker-php-ext-install mysqli

WORKDIR /app

EXPOSE 8080

CMD php -S 0.0.0.0:${PORT:-8080}
