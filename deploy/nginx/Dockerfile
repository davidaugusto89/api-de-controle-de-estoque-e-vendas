FROM nginx:alpine
WORKDIR /var/www/html
# Copiamos apenas a pasta public (contém index.php e assets)
COPY ./backend/public /var/www/html/public
COPY ./deploy/nginx/default.conf /etc/nginx/conf.d/default.conf
