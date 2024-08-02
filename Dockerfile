FROM php:8.3-cli-alpine

WORKDIR /var/www
COPY . .
RUN docker-php-ext-install mysqli

CMD ["tail", "-f", "/dev/null"]
