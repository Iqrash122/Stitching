<?php

use App\Http\Controllers\StudioController;
use App\Http\Middleware\OwnerOnly;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/studio')->name('home');
Route::redirect('/dashboard', '/studio')->middleware(['auth', OwnerOnly::class])->name('dashboard');
Route::middleware(['auth', OwnerOnly::class])->group(function () {
    Route::get('/studio', [StudioController::class, 'index'])->name('studio');
    Route::get('/studio/customers/{customer}', [StudioController::class, 'customer']);
    Route::get('/studio/orders/{order}', [StudioController::class, 'order']);
    Route::get('/studio/{section}', [StudioController::class, 'index']);
    Route::post('/studio/customers/{id?}', [StudioController::class, 'saveCustomer']);
    Route::post('/studio/config/{kind}/{id?}', [StudioController::class, 'saveConfig']);
    Route::post('/studio/logo', [StudioController::class, 'logo']);
    Route::post('/studio/orders', [StudioController::class, 'createOrder']);
    Route::post('/studio/orders/{order}/edit', [StudioController::class, 'editOrder']);
    Route::post('/studio/orders/{order}/snapshot/{snapshot}/copy', [StudioController::class, 'copyPieces']);
    Route::post('/studio/preview', [StudioController::class, 'preview']);
    Route::post('/studio/orders/{order}/transition', [StudioController::class, 'transition']);
    Route::post('/studio/orders/{order}/snapshot/{snapshot}', [StudioController::class, 'snapshot']);
    Route::post('/studio/orders/{order}/repeat', [StudioController::class, 'repeat']);
    Route::post('/studio/finance/{action}/{id?}', [StudioController::class, 'finance'])->middleware('throttle:60,1');
    Route::post('/studio/follow-ups/{id?}', [StudioController::class, 'followup']);
    Route::get('/studio-download/{id}', [StudioController::class, 'download']);
    Route::post('/studio-exports/{id}/retry', [StudioController::class, 'retryExport']);
    Route::post('/studio-export/{type}', [StudioController::class, 'export']);
    Route::get('/studio-report-pdf/{type}', [StudioController::class, 'reportPdf']);
    Route::get('/studio-document/{type}/{id}', [StudioController::class, 'document']);
});
require __DIR__.'/settings.php';
