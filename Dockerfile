FROM php:8.2-apache

# Apache mod_rewrite সক্রিয় করা
RUN a2enmod rewrite

# .htaccess কার্যকর করতে AllowOverride All এনাবল করা
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# পিএইচপি এবং মাইএসকিউএল ডাটাবেজ এক্সটেনশন ইনস্টল
RUN docker-php-ext-install pdo pdo_mysql

# প্রজেক্টের সমস্ত সোর্স ফাইল কপি করা
COPY . /var/www/html/

# ওয়েব সার্ভারের পারমিশন ঠিক করা
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
