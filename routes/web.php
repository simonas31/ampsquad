<?php

declare(strict_types=1);

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::localized(function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('contact.store');

    // Catch-all for admin-managed static pages (about, privacy-policy,
    // terms-and-conditions, and whatever else gets added later) — must
    // stay last so the literal routes above take precedence.
    Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
});
