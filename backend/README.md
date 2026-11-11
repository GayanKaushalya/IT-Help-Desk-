# IT Helpdesk — Backend

Laravel 13 REST API backed by Microsoft SQL Server.

```powershell
composer install      # first run only
php artisan migrate
php artisan db:seed
php artisan serve     # http://localhost:8000

php artisan db:show   # verify the database connection
php artisan test
./vendor/bin/pint     # format
```

Configuration is read from `.env`; copy `.env.example` and fill in the database credentials.

Full setup instructions, schema reference and development standards are in the
[project README](../README.md).
