cd /data/api;
chmod -R 777 ./storage;
php artisan laravel:test;
php artisan laravel:count;
