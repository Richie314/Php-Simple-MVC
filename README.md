# Php-Simple-MVC
Very basic php library for a MVC (Model-View-Control) application

```bash
composer require richie314/simple-mvc
```

## Webserver configuration

### Apache


### Nginx

In this example the nginx server listens on port 8080 and redirectes all the traffic to `index.php`, except for the `Public` folder, where is assumed there are public static files that will be served without redirecting the request to the `index.php` file first.

In the configuration below, php is accessed via a unix socket.
 
```conf
server {
    server_name example;
    listen 8080 default_server;
    root /example;
    index index.php;

    location ~* ^/Public/ {
        try_files $uri $uri/ /index.php$is_args$args;

        # Enable compression for static assets
        gzip on;
        gzip_types text/plain text/css application/javascript application/json image/svg+xml application/xml font/woff2;
        gzip_vary on;

        # Cache static assets
        location ~* \.(?:css|js|jpg|jpeg|gif|png|ico|svg|webp|woff|woff2|ttf|eot)$ {
            expires 30d;
            add_header Cache-Control "public, immutable";
        }
    }

    location / {
        rewrite ^ /index.php last;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php-app.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```