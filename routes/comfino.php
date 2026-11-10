<?php

declare(strict_types=1);

use Beziworld\Comfino\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$middleware = Config::get('comfino.notifications.middleware', []);
$path = trim((string) Config::get('comfino.notifications.path', 'comfino/notification'), '/');

Route::match(['PUT', 'POST'], $path . '/{connection?}', NotificationController::class)
    ->middleware(is_array($middleware) ? $middleware : [])
    ->where('connection', '[A-Za-z0-9_.\-]+')
    ->name((string) Config::get('comfino.notifications.name', 'comfino.notification'));
