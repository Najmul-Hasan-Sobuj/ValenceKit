<?php

use App\Http\Controllers\Api\LeadWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/v1/leads/webhook', LeadWebhookController::class)
    ->middleware(['throttle:60,1', 'auth:leads'])
    ->name('api.leads.webhook');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['throttle:api', 'auth:api']);
