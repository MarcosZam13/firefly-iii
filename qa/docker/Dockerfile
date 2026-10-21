# Entorno de pruebas del proyecto de QA: PHP 8.5 CLI + Composer con las
# extensiones que pide composer.json de Firefly III. La imagen oficial
# fireflyiii/core no trae dependencias de desarrollo (PHPUnit, Mockery).
FROM php:8.5-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
 && docker-php-ext-install bcmath intl zip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
