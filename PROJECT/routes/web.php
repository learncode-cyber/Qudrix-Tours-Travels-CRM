<?php

use Illuminate\Support\Facades\Route;

// This is a pure API backend (see routes/api.php, routes/api-public.php).
// Nothing is served here yet.

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
    ]);
});
