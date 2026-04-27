<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    | The Eloquent model that holds health check history.
    | Must have columns: id, check_name, status, short_summary,
    | notification_message, created_at.
    */
    'model' => \Spatie\Health\Models\HealthCheckResultHistoryItem::class,

    /*
    |--------------------------------------------------------------------------
    | Brand colour
    |--------------------------------------------------------------------------
    | Hex colour used for the status hero bar, active filter buttons, and the
    | operational dot. Accepts any valid 6-digit hex value (with or without #).
    */
    'brand_color'    => env('STATUS_PAGE_BRAND_COLOR', '#2563eb'),

    /*
    |--------------------------------------------------------------------------
    | Brand logo URL
    |--------------------------------------------------------------------------
    | Optional. When set, an <img> tag is shown in the header and footer instead
    | of the plain app name text. Use an absolute URL or an asset() path.
    | Leave null to show the app name as text.
    */
    'brand_logo_url' => env('STATUS_PAGE_LOGO_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled'    => true,
        'prefix'     => '',
        'middleware' => ['web'],
        'status'     => 'status',
        'history'    => 'status/history',
    ],

    /*
    |--------------------------------------------------------------------------
    | History settings
    |--------------------------------------------------------------------------
    */
    'incidents_per_page'           => (int) env('STATUS_PAGE_INCIDENTS_PER_PAGE', 7),
    'incident_stability_minutes'   => 15,
    'history_retention_days'       => 90,

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    | Define service groups and which health check names belong to each.
    |
    | degraded_only — when true, failures in this service make the page show
    |                 "Degraded" rather than "Down".
    | enabled       — set to false (or use an env var) to hide the service
    |                 entirely from the status page and history queries.
    */
    'services' => [

        'api' => [
            'label'        => 'API',
            'checks'       => ['Cache', 'Database', 'Redis', 'DebugMode', 'Environment', 'OptimizedApp', 'SecurityAdvisories'],
            'degraded_only' => false,
            'enabled'      => true,
        ],

        'jobs' => [
            'label'        => 'Queue Management',
            'checks'       => ['Horizon', 'Schedule', 'Queue'],
            'degraded_only' => false,
            'enabled'      => true,
        ],

        'payment' => [
            'label'        => 'Payment Processing',
            'checks'       => ['stripe', 'spreedly'],
            'degraded_only' => true,
            'enabled'      => (bool) env('STATUS_PAGE_PAYMENT_ENABLED', true),
        ],

        'email' => [
            'label'        => 'Email Delivery',
            'checks'       => ['mailgun'],
            'degraded_only' => true,
            'enabled'      => (bool) env('STATUS_PAGE_EMAIL_ENABLED', true),
        ],

        'storage' => [
            'label'        => 'Storage',
            'checks'       => ['aws_s3', 'aws_s3_admin'],
            'degraded_only' => true,
            'enabled'      => (bool) env('STATUS_PAGE_STORAGE_ENABLED', true),
        ],

        'image_processing' => [
            'label'        => 'Image Processing',
            'checks'       => ['inkscape', 'rsvg_convert'],
            'degraded_only' => true,
            'enabled'      => (bool) env('STATUS_PAGE_IMAGE_PROCESSING_ENABLED', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Check labels
    |--------------------------------------------------------------------------
    | Human-readable names for individual check names shown on the status page.
    */
    'check_labels' => [
        'Cache'              => 'Redis Cache',
        'Database'           => 'MySQL Database',
        'Redis'              => 'Redis Connection',
        'DebugMode'          => 'Debug Mode',
        'Environment'        => 'Environment',
        'OptimizedApp'       => 'Application Optimization',
        'SecurityAdvisories' => 'Security Updates',
        'Queue'              => 'Background Job Queue',
        'Horizon'            => 'Queue Dashboard',
        'Schedule'           => 'Scheduled Tasks',
    ],

];
