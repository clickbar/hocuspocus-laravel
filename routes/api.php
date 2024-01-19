<?php

use Hocuspocus\HocuspocusLaravel;
use Hocuspocus\HocuspocusMiddleware;

Route::middleware([HocuspocusMiddleware::class, 'web', 'auth'])->post(config('hocuspocus-laravel.route'), [HocuspocusLaravel::class, 'handleWebhook']);
