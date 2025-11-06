# How to Display todo5.php on a Website

1) Prerequisites
- PHP 8.x installed
- Web server installed (Apache or Nginx) or PHP built-in server
- File: todo5.php
- Optional: Database and required PHP extensions if the script depends on them

2) Place the File
- Copy todo5.php into your web root:
  - Linux (Apache): /var/www/html
  - Linux (Nginx): /usr/share/nginx/html or /var/www/html
  - Windows (XAMPP): C:\xampp\htdocs
  - macOS (MAMP): /Applications/MAMP/htdocs
  - cPanel: public_html
  - Plesk: httpdocs
- Permissions (Linux):
  - chmod 644 todo5.php
  - chmod 755 directories
  - chown -R www-data:www-data /var/www/html (Debian/Ubuntu; adjust user/group as needed)

3) Quick Local Test (no web server config)
- From the directory containing todo5.php:
  - php -S localhost:8000
  - Visit http://localhost:8000/todo5.php

4) Run with Docker (optional)
- In the folder containing todo5.php:
  - docker run --rm -p 8080:80 -v "$PWD":/var/www/html php:8.3-apache
  - Visit http://localhost:8080/todo5.php

5) Apache Setup (PHP-FPM)
- Install PHP-FPM and Apache PHP integration (Debian/Ubuntu):
  - sudo apt install apache2 php8.3-fpm
  - sudo a2enmod proxy_fcgi setenvif
  - sudo a2enconf php8.3-fpm
  - sudo systemctl reload apache2
- Minimal VirtualHost (if using a custom doc root):
  - /etc/apache2/sites-available/site.conf
    <VirtualHost *:80>
      ServerName example.com
      DocumentRoot /var/www/html
      <Directory /var/www/html>
        AllowOverride All
        Require all granted
      </Directory>
    </VirtualHost>
  - sudo a2ensite site && sudo systemctl reload apache2
- Visit http://your-domain-or-ip/todo5.php

6) Nginx Setup (PHP-FPM)
- Install Nginx and PHP-FPM:
  - sudo apt install nginx php8.3-fpm
- Server block (e.g., /etc/nginx/sites-available/site):
    server {
      listen 80;
      server_name example.com;
      root /var/www/html;
      index index.php index.html;

      location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
      }
    }
  - sudo ln -s /etc/nginx/sites-available/site /etc/nginx/sites-enabled/site
  - sudo nginx -t && sudo systemctl reload nginx
- Visit http://your-domain-or-ip/todo5.php

7) Configure Dependencies (if required by todo5.php)
- Database: create DB/user, grant privileges
- Update credentials in a config file or environment variables
- Ensure required PHP extensions (e.g., pdo_mysql) are installed:
  - sudo apt install php8.3-mysql
  - sudo systemctl reload apache2 or php8.3-fpm

8) Production Settings
- Use HTTPS (obtain a certificate via Let’s Encrypt)
- Set display_errors=Off in php.ini; log errors instead
- Disable directory listing
- Do not commit secrets; store them in environment variables or secured config files

9) Troubleshooting
- 404 Not Found: verify document root and file location
- Blank page/error 500: check logs
  - Apache: /var/log/apache2/error.log
  - Nginx: /var/log/nginx/error.log and PHP-FPM logs
- Wrong PHP version or missing extensions: php -v, php -m
- Permissions/SELinux: adjust ownership/contexts accordingly

After setup, access:
- Local: http://localhost/todo5.php
- Remote: http://your-domain-or-ip/todo5.php