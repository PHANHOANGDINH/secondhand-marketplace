<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Secondhand Marketplace API', 'health' => '/api/v1/health']);
});
