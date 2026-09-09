<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\AdminMediaController;
use App\Http\Middleware\EnsureAuthenticatedAdmin;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/preview/{token}/{path?}', PreviewController::class)
    ->where('path', '.*')
    ->middleware(EnsureAuthenticatedAdmin::class)
    ->name('admin.preview');

Route::get('/admin/media/{mediaAsset}', AdminMediaController::class)
    ->middleware(EnsureAuthenticatedAdmin::class)
    ->name('admin.media');
