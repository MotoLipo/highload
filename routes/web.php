<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Spatie\Prometheus\Facades\Prometheus;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function (ClickHouseDB\Client $client) {
    try {
        Cache::get('health-check');
        $client->select('SELECT 1');
    } catch (Throwable $e) {
        return response()->json(['status' => 'degraded', 'error' => $e->getMessage()], 503);
    }

    return response()->json(['status' => 'ok']);
});

Route::get('/metrics', function () {
    return response(Prometheus::renderCollectors())
        ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
});