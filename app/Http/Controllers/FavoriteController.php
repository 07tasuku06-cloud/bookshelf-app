<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index()
    {
        $books = auth()->user()
            ->favoriteBooks()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }
    //
}
