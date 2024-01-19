<?php

use Hocuspocus\HocuspocusLaravel;

Route::middleware(['web', 'auth'])->post(config('hocuspocus-laravel.route'), [HocuspocusLaravel::class, 'handleWebhook']);
