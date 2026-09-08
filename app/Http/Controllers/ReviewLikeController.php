<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    public function toggle(Review $review): RedirectResponse
    {
        auth()->user()->likedReviews()->toggle($review->id);

        return redirect()->route('books.show', $review->book_id);
    }
}
