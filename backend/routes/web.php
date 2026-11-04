<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app/'));

// Back-office (Vue) : toutes les URL /app/* renvoient l'index de l'appli.
Route::get('/app/{any?}', function () {
    $index = public_path('app/index.html');
    abort_unless(is_file($index), 503, "L'interface web n'est pas encore compilée.");

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('any', '.*');

// Site public de chaque garage.
Route::prefix('s/{slug}')->name('site.')->group(function () {
    Route::get('/', [SiteController::class, 'index'])->name('index');
    Route::get('/voiture/{id}', [SiteController::class, 'show'])->whereNumber('id')->name('show');
    Route::get('/garage', [SiteController::class, 'about'])->name('about');
    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
    Route::post('/contact', [SiteController::class, 'sendLead'])->middleware('throttle:6,1')->name('lead');
    Route::get('/merci', [SiteController::class, 'thanks'])->name('thanks');
    Route::get('/informations', [SiteController::class, 'legal'])->name('legal');
});
