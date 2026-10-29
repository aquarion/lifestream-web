<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/version', function () {
    return response()->json(config('version'));
})->name('version');

Route::post('/feed', FeedController::class)->name('feed');
Route::any('/fetchNext.php', FeedController::class);

Route::get('/maps', [MapController::class, 'heatmap'])->name('maps');
Route::get('/today', [MapController::class, 'today'])->name('today');
Route::get('/lastseen', [MapController::class, 'lastSeen'])->name('lastseen');

// Legacy .php URLs, keeping any query string (e.g. maps.php?year=2019).
foreach (['/maps.php' => '/maps', '/today.php' => '/today', '/lastseen.php' => '/lastseen', '/index.php' => '/'] as $legacy => $current) {
    Route::get($legacy, fn (Request $request) => redirect($current.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301));
}

Route::redirect('/cv', 'https://docs.google.com/document/d/1ToerY5WNhYYTh1xLy-IGf1h8n-CVygtynQQ1Nn7eXsk/view');

// Everything else is a lifestream page: /, /2024, /2024/wk12, /2024/03,
// /2024/03/05, /*/03/05 or /search/term. HomeController 404s on paths it
// doesn't recognise.
Route::get('/{path?}', HomeController::class)->where('path', '.*')->name('home');
