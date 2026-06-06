FROM php:8.3-cli

# Install pdo_mysql and other required extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install curl extension
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Copy app files
COPY . /app

WORKDIR /app

EXPOSE 8080

CMD php -S 0.0.0.0:$PORT -t /app
