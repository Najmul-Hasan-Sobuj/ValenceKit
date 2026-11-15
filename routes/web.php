<?php

use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SeoController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('llms.txt', [SeoController::class, 'llms'])->name('llms');

Route::inertia('/', 'Welcome')->name('home');
Route::post('contact', [ContactMessageController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::inertia('services', 'Services')->name('services');
Route::inertia('privacy-policy', 'PrivacyPolicy')->name('privacy-policy');
Route::inertia('terms', 'Terms')->name('terms');

Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('read-all', 'readAll')->name('read-all');
        Route::patch('{notification}/read', 'read')->whereUuid('notification')->name('read');
        Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
    });
});

require __DIR__.'/settings.php';
