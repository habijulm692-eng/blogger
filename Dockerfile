FROM php:8.2-apache

# Apache URL rewrite সক্রিয় করা
RUN a2enmod rewrite

# পিএইচপি এবং মাইএসকিউএল ডাটাবেজ এক্সটেনশন ইনস্টল
RUN docker-php-ext-install pdo pdo_mysql

# প্রজেক্টের সব ফাইল অ্যাপাচে ডিরেক্টরিতে কপি করা
COPY . /var/www/html/

# ফাইল ও ফোল্ডারের পারমিশন সেট করা
RUN chown -R www-data:www-data /var/www/html

# পোর্ট এক্সপোজ করা
EXPOSE 80

# অ্যাপাচে সার্ভার ব্যাকগ্রাউন্ডে চালু রাখা
CMD ["apache2-foreground"]
