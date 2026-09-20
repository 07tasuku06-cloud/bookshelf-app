<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * 認証ユーザーのお気に入り書籍一覧を表示する。
     *
     * @return View お気に入り書籍一覧画面
     */
    public function index(): View
    {
        $books = auth()->user()
            ->favoriteBooks()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    /**
     * 指定された書籍のお気に入り状態を切り替える。
     *
     * @param  Book  $book  対象書籍
     * @return RedirectResponse 直前の画面へのリダイレクト
     */
    public function toggle(Book $book): RedirectResponse
    {
        auth()->user()
            ->favoriteBooks()
            ->toggle($book->id);

        return back()->with(
            'success', 'お気に入り状態を更新しました。'
        );
    }
}
