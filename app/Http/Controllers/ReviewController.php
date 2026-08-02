<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Book $book)
    {
        $validated = $request->validated();

        $validated['user_id'] = auth()->id();
        $validated['book_id'] = $book->id;

        Review::create($validated);

        return redirect()->route('books.show', $book);
    }

    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));

    }

    public function update(UpdateReviewRequest $request, Review $review)
    {

        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()->route('books.show', $review->book_id);

    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $bookId = $review->book_id;

        $review->delete();

        return redirect()->route('books.show', $bookId);
    }

}
