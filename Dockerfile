FROM php:8.2-apache

# تفعيل ميزة تعيد توجيه جميع الطلبات إلى ملف index.php
RUN a2enmod rewrite

# ضبط المجلد الرئيسي وإعدادات الخادم
COPY . /var/www/html/

# تغيير منفذ Apache للعمل مع Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 80
