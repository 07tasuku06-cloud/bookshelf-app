<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        $books = auth()->user()
            ->favoriteBooks()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    public function toggle(Book $book): RedirectResponse
    {
        auth()->user()
            ->favoriteBooks()
            ->toggle($book->id);

        return back();
    }
    //
}
