<?php

use App\Http\Controllers\ExternalPreviewRunnerController;
use App\Http\Controllers\ContactInquiryController;
use App\Http\Controllers\PublisherPublicationController;
use App\Http\Middleware\EnsureContactOrigin;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureContactOrigin::class)->group(function (): void {
    Route::options('/contact', [ContactInquiryController::class, 'options']);
    Route::post('/contact', [ContactInquiryController::class, 'store'])->middleware('throttle:contact');
});

Route::middleware('throttle:30,1')->prefix('publisher/v1')->group(function (): void {
    Route::get('/publications/{publication}/package', [PublisherPublicationController::class, 'package']);
    Route::post('/publications/{publication}/status', [PublisherPublicationController::class, 'status']);
});

Route::middleware('throttle:30,1')->prefix('preview-runner/v1')->group(function (): void {
    Route::get('/previews/{preview}/package', [ExternalPreviewRunnerController::class, 'package']);
    Route::post('/previews/{preview}/status', [ExternalPreviewRunnerController::class, 'status']);
});
