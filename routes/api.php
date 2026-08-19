<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::apiResource('books', BookController::class)
        ->only([
            'index',
            'store',
            'show',
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
