<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PracticeSessionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('pieces.index') : view('welcome'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/pieces', [PieceController::class, 'index'])->name('pieces.index');
    Route::get('/pieces/create', [PieceController::class, 'create'])->name('pieces.create');
    Route::post('/pieces', [PieceController::class, 'store'])->name('pieces.store');
    Route::get('/pieces/{piece}', [PieceController::class, 'show'])->name('pieces.show');
    Route::get('/pieces/{piece}/file', [PieceController::class, 'file'])->name('pieces.file');
    Route::post('/pieces/{piece}/confirm', [PieceController::class, 'confirm'])->name('pieces.confirm');
    Route::get('/pieces/{piece}/pdf', [PieceController::class, 'pdf'])->name('pieces.pdf');
    Route::post('/pieces/{piece}/use-pdf', [PieceController::class, 'usePdf'])->name('pieces.use-pdf');
    Route::get('/pieces/{piece}/edit', [PieceController::class, 'edit'])->name('pieces.edit');
    Route::put('/pieces/{piece}', [PieceController::class, 'update'])->name('pieces.update');
    Route::delete('/pieces/{piece}', [PieceController::class, 'destroy'])->name('pieces.destroy');

    Route::get('/pieces/{piece}/play-pdf', [PlayerController::class, 'pdf'])->name('player.pdf');
    Route::get('/pieces/{piece}/play', [PlayerController::class, 'show'])->name('player.show');
    Route::get('/tuner', [PlayerController::class, 'tuner'])->name('tuner');

    Route::get('/sessions/{session}', [PracticeSessionController::class, 'show'])->name('sessions.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
