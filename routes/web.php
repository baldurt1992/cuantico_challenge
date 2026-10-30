<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\DeliveryEventController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('operations'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:20,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations');
    Route::post('/api/v1/events', [DeliveryEventController::class, 'store'])->middleware('throttle:120,1')->name('events.store');
    Route::get('/api/v1/merchants/{merchant}/operations', [OperationsController::class, 'data'])->name('operations.data');
    Route::get('/api/v1/merchants/{merchant}/deliveries/{delivery}', [OperationsController::class, 'detail'])->name('deliveries.show');
    Route::post('/api/v1/merchants/{merchant}/chat', [ChatController::class, 'store'])->name('chat.store');
});
