# CMS

Courier SaaS CMS built with Laravel.

## Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
```

Point the web server document root to `public`.
