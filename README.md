# laravel-health-panel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elvinaqalarov99/laravel-health-panel.svg?style=flat-square)](https://packagist.org/packages/elvinaqalarov99/laravel-health-panel)
[![License](https://img.shields.io/github/license/elvinaqalarov99/laravel-health-panel.svg?style=flat-square)](LICENSE)

A beautiful, config-driven status panel for Laravel built on top of [spatie/laravel-health](https://spatie.be/docs/laravel-health/v1/introduction).

`spatie/laravel-health` does the heavy lifting — running checks, storing results, alerting. **laravel-health-panel** turns those results into a polished public status page with uptime history, incident timelines, and zero boilerplate.

---

## What it gives you

| | |
|---|---|
| **`/status`** | Live status of every service group — Operational / Degraded / Down |
| **`/status/history`** | 90-day uptime bars + incident timeline with noise reduction |
| **Config-driven** | Define services, checks, labels and feature flags in one file — no PHP class changes |
| **Swappable** | Bind your own service or repository via contracts |
| **Publishable views** | Override the default dark-mode Tailwind UI with your own Blade templates |

---

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- [spatie/laravel-health](https://spatie.be/docs/laravel-health/v1/introduction) — health checks must already be set up

---

## Installation

```bash
composer require elvinaqalarov99/laravel-health-panel
```

Publish the config and migration:

```bash
php artisan vendor:publish --tag=status-page:config
php artisan vendor:publish --tag=status-page:migrations
php artisan migrate
```

Visit `/status` — you're live.

---

## Configuration

All behaviour is controlled from `config/status-page.php`.

### Defining services

Map your Spatie health check names into logical service groups:

```php
'services' => [

    'api' => [
        'label'         => 'API',
        'checks'        => ['Cache', 'Database', 'Redis', 'Queue'],
        'degraded_only' => false,   // failures here → system DOWN
        'enabled'       => true,
    ],

    'payment' => [
        'label'         => 'Payment Processing',
        'checks'        => ['stripe', 'spreedly'],
        'degraded_only' => true,    // failures here → system DEGRADED, not down
        'enabled'       => env('STATUS_PAGE_PAYMENT_ENABLED', true),
    ],

    'email' => [
        'label'         => 'Email Delivery',
        'checks'        => ['mailgun'],
        'degraded_only' => true,
        'enabled'       => env('STATUS_PAGE_EMAIL_ENABLED', true),
    ],

],
```

Set `enabled` to `false` (or wire it to an env var) to hide a service entirely — it disappears from the status page, uptime bars, and all history queries.

### Check labels

Give individual check names a human-readable label shown on the panel:

```php
'check_labels' => [
    'Cache'    => 'Redis Cache',
    'Database' => 'MySQL Database',
    'stripe'   => 'Stripe',
],
```

Any check not listed here falls back to a title-cased version of the name.

### Routes

```php
'routes' => [
    'enabled'    => true,
    'prefix'     => '',              // mount under /admin by setting 'admin'
    'middleware' => ['web'],
    'status'     => 'status',        // → /status
    'history'    => 'status/history', // → /status/history
],
```

Disable auto-registration entirely and define your own routes:

```php
// config/status-page.php
'routes' => ['enabled' => false],
```

```php
// routes/web.php
use Elvinaqalarov99\StatusPage\Http\Controllers\StatusPageController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/status',         [StatusPageController::class, 'index'])->name('status-page.index');
    Route::get('/admin/status/history', [StatusPageController::class, 'history'])->name('status-page.history');
});
```

### History settings

```php
'incidents_per_page'         => 7,   // date-groups shown per history page
'incident_stability_minutes' => 15,  // gaps shorter than this merge into one incident
'history_retention_days'     => 90,  // days shown in the uptime bar
```

### Swapping the model

By default the panel reads from Spatie's `HealthCheckResultHistoryItem`. Swap it with any model that has the same columns:

```php
'model' => \App\Models\MyHealthResult::class,
```

---

## Customising the UI

Publish the default views:

```bash
php artisan vendor:publish --tag=status-page:views
```

Views land in `resources/views/vendor/status-page/`. Edit freely — the panel respects Laravel's standard view cascade so your versions always take precedence.

The default views use Tailwind CSS via CDN and require no build step.

---

## Extending the service layer

Every component is bound through a contract, so you can swap any layer from your `AppServiceProvider`:

```php
use Elvinaqalarov99\StatusPage\Contracts\StatusPageServiceContract;
use Elvinaqalarov99\StatusPage\Contracts\StatusPageRepositoryContract;

// Add a caching layer on top of the default service
$this->app->bind(StatusPageServiceContract::class, CachedStatusPageService::class);

// Use a different data source entirely
$this->app->bind(StatusPageRepositoryContract::class, MyStatusPageRepository::class);
```

---

## How it works

```
spatie/laravel-health
  └── runs checks every minute (via scheduler)
  └── stores results in health_check_result_history_items

laravel-health-panel
  └── StatusPageRepository   reads history with bounded queries (no OOM)
  └── StatusPageService      groups checks → services, detects incidents,
  │                          reduces noise, calculates uptime %
  └── StatusPageController   thin — calls service, returns view
  └── Views                  dark-mode Tailwind UI, fully publishable
```

Key performance decisions:
- **Latest status** uses `MAX(id) GROUP BY check_name` — two small queries instead of unbounded full-table scans
- **Uptime bars** are aggregated entirely in MySQL (`GROUP BY day, check_name`) — never loaded as Eloquent models
- **Incident history** selects only needed columns and caps rows at 100k
- A `(check_name, created_at)` composite index is included in the published migration

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
