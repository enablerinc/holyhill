FROM php:8.2-fpm-alpine

# 타임존 설정
ENV TZ=Asia/Seoul
RUN apk add --no-cache tzdata && \
    cp /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# 런타임 패키지 설치
RUN apk add --no-cache \
    freetype \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    icu-libs

# 빌드 의존성 설치
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev

# GD 설정
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

# 표준 PHP 확장 설치
RUN docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    pdo_mysql \
    exif \
    opcache \
    zip \
    intl

# Redis 확장 설치 (PECL)
RUN pecl install redis-6.0.2 && \
    docker-php-ext-enable redis

# 빌드 도구 제거
RUN apk del .build-deps && \
    rm -rf /tmp/pear

# 작업 디렉토리
WORKDIR /var/www/html

# www-data 사용자의 UID/GID 설정 (Alpine의 기본값)
# data 디렉토리 권한 설정
RUN mkdir -p /var/www/html/data/file \
    /var/www/html/data/cache \
    /var/www/html/data/session \
    /var/www/html/data/log && \
    chown -R www-data:www-data /var/www/html/data && \
    chmod -R 775 /var/www/html/data

# Entrypoint 스크립트 복사 및 실행 권한 설정
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# PHP-FPM 설정
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]