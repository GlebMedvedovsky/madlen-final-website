<?php

use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\PreviewController;
use App\Http\Middleware\EnsureAuthenticatedAdmin;
use Illuminate\Support\Facades\Route;

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

Route::get('/admin/session/csrf-token', [AdminSessionController::class, 'csrfToken'])
    ->middleware(EnsureAuthenticatedAdmin::class)
    ->name('admin.session.csrf');
