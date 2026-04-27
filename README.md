# laravel-status-page

A config-driven, zero-boilerplate status page for Laravel apps powered by [spatie/laravel-health](https://github.com/spatie/laravel-health).

## Installation

```bash
composer require elvinaqalarov99/laravel-status-page
php artisan vendor:publish --tag=status-page:config
php artisan vendor:publish --tag=status-page:migrations
php artisan migrate
```

## Usage

Visit `/status` — it works out of the box.

Edit `config/status-page.php` to define your services:

```php
'services' => [
    'api' => [
        'label'        => 'API',
        'checks'       => ['Cache', 'Database', 'Redis'],
        'degraded_only' => false,
        'enabled'      => true,
    ],
    'payment' => [
        'label'        => 'Payment',
        'checks'       => ['stripe'],
        'degraded_only' => true,                              // failures → "Degraded", not "Down"
        'enabled'      => env('STATUS_PAGE_PAYMENT_ENABLED', true),
    ],
],
```

## Customising views

```bash
php artisan vendor:publish --tag=status-page:views
```

Views will be placed in `resources/views/vendor/status-page/`.

## Swapping implementations

Bind your own service or repository in `AppServiceProvider`:

```php
use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;

$this->app->bind(StatusPageServiceContract::class, MyStatusPageService::class);
```

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `model` | `HealthCheckResultHistoryItem` | Eloquent model for check history |
| `routes.enabled` | `true` | Register `/status` routes automatically |
| `routes.prefix` | `''` | URL prefix |
| `routes.middleware` | `['web']` | Route middleware |
| `incidents_per_page` | `7` | Date groups per history page |
| `incident_stability_minutes` | `15` | Gap between outages before they're treated as separate incidents |
| `history_retention_days` | `90` | Days shown in the uptime bar |

## License

MIT
