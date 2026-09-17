<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/events', [EventController::class, 'store'])
    ->middleware('throttle:events');

Route::get('/analytics/page-views', [AnalyticsController::class, 'pageViews'])
    ->middleware('auth:sanctum');
Route::get('/analytics/conversion', [AnalyticsController::class, 'conversion'])
    ->middleware('auth:sanctum');
