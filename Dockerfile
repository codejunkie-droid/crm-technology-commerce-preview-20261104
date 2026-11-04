FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install curl mbstring pdo_mysql pdo_sqlite \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/crm-technology
COPY . /var/www/crm-technology

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '  DocumentRoot /var/www/crm-technology/public' \
    '  <Directory /var/www/crm-technology/public>' \
    '    Options -Indexes +FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '  </Directory>' \
    '  ErrorLog /dev/stderr' \
    '  CustomLog /dev/stdout combined' \
    '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data /var/www/crm-technology/storage

ENV APP_ENV=demo \
    DB_DSN=sqlite:/var/www/crm-technology/storage/commerce.sqlite \
    PORT=10000

EXPOSE 10000
CMD ["/var/www/crm-technology/deploy/start-render.sh"]
