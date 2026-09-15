<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * 指定されたレビューのいいね状態を切り替える。
     *
     * @param  Review  $review  対象レビュー
     * @return RedirectResponse 直前の画面へのリダイレクト
     */
    public function toggle(Review $review): RedirectResponse
    {
        auth()->user()->likedReviews()->toggle($review->id);

        return redirect()->route('books.show', $review->book_id);
    }
}
