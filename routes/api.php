<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // トークン発行は未認証でも利用可能
    Route::post('/tokens', [AuthTokenController::class, 'store'])
        ->name('tokens.store');

    // 書籍の閲覧APIは公開
    Route::apiResource('books', BookController::class)
        ->only([
            'index',
            'show',
        ])
        ->missing(function () {
            return response()->json(
                [
                    'message' => '指定された書籍は存在しません。',
                ],
                404
            );
        });

    // トークン認証が必要なAPI
    Route::middleware('auth:sanctum')->group(function () {
        Route::delete(
            '/tokens/current',
            [AuthTokenController::class, 'destroy']
        )->name('tokens.destroy');

        Route::apiResource('books', BookController::class)
            ->only([
                'store',
                'update',
                'destroy',
            ])
            ->missing(function () {
                return response()->json(
                    [
                        'message' => '指定された書籍は存在しません。',
                    ],
                    404
                );
            });
    });
});
