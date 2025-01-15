FROM php:8.4-fpm

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Install AWS CLI
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    && curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" \
    && unzip awscliv2.zip \
    && ./aws/install \
    && rm -rf aws awscliv2.zip

# Copy AWS credentials and config
COPY ~/.aws /root/.aws

WORKDIR /var/www/html
