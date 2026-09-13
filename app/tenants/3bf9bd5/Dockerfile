FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx \
 && ln -sf /dev/stdout /var/log/nginx/access.log \
 && ln -sf /dev/stderr /var/log/nginx/error.log

COPY nginx/default.conf /etc/nginx/http.d/default.conf
COPY src/               /var/www/html/

EXPOSE 80

CMD php-fpm -D && nginx -g 'daemon off;'
