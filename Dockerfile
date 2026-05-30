FROM php:8.2-cli

WORKDIR /app

# Install mysqli + pdo_mysql extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . .

CMD php -S 0.0.0.0:$PORT