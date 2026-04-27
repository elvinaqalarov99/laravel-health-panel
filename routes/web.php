<?php

use Elvinaqalarov99\StatusPage\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('status-page.routes.middleware', ['web']))
    ->prefix(config('status-page.routes.prefix', ''))
    ->group(function () {
        Route::get(
            config('status-page.routes.status', 'status'),
            [StatusPageController::class, 'index']
        )->name('status-page.index');

        Route::get(
            config('status-page.routes.history', 'status/history'),
            [StatusPageController::class, 'history']
        )->name('status-page.history');
    });
