<?php

use App\Http\Controllers\Api\PieceController;
use App\Http\Controllers\Api\PracticeSessionController;
use App\Services\PlayerTokenService;
use Illuminate\Support\Facades\Route;

// Called by the browser player with the Sanctum token it gets on page load (see PlayerTokenService).
Route::middleware(['auth:sanctum', 'verified', 'abilities:'.PlayerTokenService::ABILITY])->group(function () {
    Route::get('/pieces/{piece}', [PieceController::class, 'show']);
    Route::post('/sessions', [PracticeSessionController::class, 'store']);
    Route::post('/sessions/{session}/results', [PracticeSessionController::class, 'storeResults']);
});
