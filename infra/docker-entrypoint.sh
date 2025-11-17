#!/bin/sh
set -e

# data 디렉토리가 존재하는지 확인하고 권한 설정
if [ -d "/var/www/html/data" ]; then
    echo "Setting permissions for /var/www/html/data directory..."

    # data 디렉토리와 하위 디렉토리 소유자를 www-data로 변경
    chown -R www-data:www-data /var/www/html/data

    # 디렉토리 권한 설정 (775: rwxrwxr-x)
    find /var/www/html/data -type d -exec chmod 775 {} \;

    # 파일 권한 설정 (664: rw-rw-r--)
    find /var/www/html/data -type f -exec chmod 664 {} \;

    echo "Permissions set successfully."
else
    echo "Warning: /var/www/html/data directory does not exist."
fi

# PHP-FPM 실행
exec "$@"
