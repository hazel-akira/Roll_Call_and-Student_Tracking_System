<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Roll Call and Student Tracking API',
        'status' => 'ok',
        'version' => 'v1',
    ]);
});
