FROM composer:2 AS composer
FROM wordpress:cli-php8.3 AS cli
FROM wordpress:php8.3-apache@sha256:5a93c470ae8220fddf71f6ebe3bc94e615ddc2ae4d9810f795b830fb11c41a17
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=cli /usr/local/bin/wp /usr/local/bin/wp
COPY server/docker/local-ca.crt /usr/local/share/ca-certificates/windows-local.crt
RUN update-ca-certificates
RUN a2enmod ssl rewrite headers && rm -rf /usr/src/wordpress
COPY --chown=www-data:www-data site/ /usr/src/wordpress/
COPY server/docker/wp-config.php /usr/src/wordpress/wp-config.php
COPY server/docker/local-development.php /usr/src/wordpress/wp-content/mu-plugins/local-development.php
COPY server/docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY server/docker/php.ini /usr/local/etc/php/conf.d/local-development.ini
COPY server/docker/bootstrap.php server/docker/verify.php server/docker/verify-http.php server/docker/lint.php server/docker/required-plugins.json /opt/wp-local/
