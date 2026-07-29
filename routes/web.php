<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use Illuminate\Support\Facades\Route;

// トップページ・書籍一覧・詳細は誰でも閲覧可能
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// ログイン必須
Route::middleware('auth')->group(function () {
    Route::resource('genres', GenreController::class);
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});

// 動的URLはcreateより後に書く
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');