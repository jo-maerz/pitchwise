<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnotationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\OrganizationMemberController;
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

    Route::get('/pieces/{piece}/annotate', [AnnotationController::class, 'show'])->name('annotations.show');
    Route::put('/pieces/{piece}/annotations/{layer}', [AnnotationController::class, 'update'])->whereIn('layer', ['shared', 'mine'])->name('annotations.update');

    Route::get('/organizations/{organization}/members', [OrganizationMemberController::class, 'index'])->name('organizations.members');
    Route::put('/organizations/{organization}/members/{user}', [OrganizationMemberController::class, 'update'])->name('organizations.members.update');

    Route::get('/folders/{folder}', [PieceController::class, 'folder'])->name('folders.show');
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');

    Route::get('/pieces/{piece}/play-pdf', [PlayerController::class, 'pdf'])->name('player.pdf');
    Route::get('/pieces/{piece}/play', [PlayerController::class, 'show'])->name('player.show');
    Route::get('/tuner', [PlayerController::class, 'tuner'])->name('tuner');

    Route::get('/sessions/{session}', [PracticeSessionController::class, 'show'])->name('sessions.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminController::class)->name('index');
        Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->name('organizations.destroy');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

require __DIR__.'/auth.php';
