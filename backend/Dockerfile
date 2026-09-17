
FROM php:8.3-cli-bookworm

RUN apt-get update \
	&& apt-get upgrade -y \
	&& apt-get install -y libcurl4-openssl-dev pkg-config \
	&& docker-php-ext-install curl \
	&& docker-php-ext-install pdo_mysql \
	&& rm -rf /var/lib/apt/lists/*

WORKDIR /app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]