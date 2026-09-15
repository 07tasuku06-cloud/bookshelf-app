<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * レビューの平均評価に基づく書籍ランキングを表示する。
     *
     * @return View 書籍ランキング画面
     */
    public function index(): View
    {
        $rankedBooks = Book::withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('id')
            ->take(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
