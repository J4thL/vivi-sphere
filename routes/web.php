<?php

use App\Http\Controllers\Web\EmailLinkController;
use App\Http\Controllers\Web\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Public marketing pages are server-rendered Blade views (real HTML,
| crawlable, fast first paint). Everything under /app is the React
| dashboard SPA - it doesn't need SSR since it's behind a login and SEO
| doesn't apply to it.
*/

Route::get('/', [PublicPageController::class, 'landing'])->name('landing');
Route::get('/stylists/{stylist}', [PublicPageController::class, 'stylistProfile'])->name('stylist.profile');
Route::get('/terms', [PublicPageController::class, 'legal'])->defaults('kind', 'terms')->name('terms');
Route::get('/privacy', [PublicPageController::class, 'legal'])->defaults('kind', 'privacy')->name('privacy');

// Targets for links inside Laravel's built-in emails. Registration and
// forgot-password both generate these by route name and crash without them.
Route::get('/email/verify/{id}/{hash}', [EmailLinkController::class, 'verify'])->middleware('signed')->name('verification.verify');
Route::get('/app/reset-password/{token}', fn () => view('spa'))->name('password.reset');

// Catch-all: /app, /app/login, /app/register, /app/anything-the-SPA-handles
// all serve the same shell; React's own state decides what to show.
Route::get('/app/{any?}', fn () => view('spa'))->where('any', '.*')->name('app');
