<?php

use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('llms.txt', [SeoController::class, 'llms'])->name('llms');

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('services', 'Services')->name('services');
Route::inertia('privacy-policy', 'PrivacyPolicy')->name('privacy-policy');
Route::inertia('terms', 'Terms')->name('terms');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
