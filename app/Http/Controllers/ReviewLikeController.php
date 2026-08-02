<?php

namespace App\Http\Controllers;

use App\Models\Review;

class ReviewLikeController extends Controller
{
    public function toggle(Review $review)
    {
        auth()->user()->likedReviews()->toggle($review->id);

        return redirect()->route('books.show', $review->book_id);
    }
}
