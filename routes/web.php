<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

// トップページ・書籍一覧・詳細は誰でも閲覧可能
Route::get('/', [BookController::class, 'index'])->name('home');
Route::get('/books', [BookController::class, 'index'])->name('books.index');

// ログイン必須ルート
Route::middleware('auth')->group(function () {
    // ジャンルCRUD
    Route::resource('genres', GenreController::class);

    // Google Books APIによるISBN検索
    Route::get('/books/isbn/{isbn}', [BookController::class, 'fetchByIsbn'])->name('books.fetch-by-isbn');

    // 書籍CRUD
    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('/books', [BookController::class, 'store'])->name('books.store');
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])->name('books.edit');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');

    // お気に入り機能
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // レビューCRUD
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // レビューいいね機能
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    // 読書計画CRUD・読了処理
    Route::post(
        '/reading-plans/{reading_plan}/complete',
        [ReadingPlanController::class, 'complete']
    )->name('reading-plans.complete');

    Route::resource('reading-plans', ReadingPlanController::class)
        ->except(['show']);

    // 通知一覧・既読処理
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});

// 動的URLはcreateより後に書く
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

// ranking表示用
Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');
