FROM php:8.2-cli

WORKDIR /app

COPY . .

# Start PHP built-in server
CMD php -S 0.0.0.0:$PORT