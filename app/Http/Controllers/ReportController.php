<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    /**
     * ログインユーザーの読書レポートを表示する。
     *
     * @param  Request  $request  認証済みユーザーを含むリクエスト
     * @return View 読書レポート画面
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $reviews = $user->reviews()
            ->with('book.genres')
            ->get();

        $stats = [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $user->readingPlans()
                    ->where(
                        'status',
                        ReadingPlanStatus::Completed->value
                    )
                    ->distinct()
                    ->count('book_id'),
                'average_rating' => (float) (
                    $reviews->avg('rating') ?? 0
                ),
            ],
            'rating_distribution' => $this
                ->buildRatingDistribution($reviews),
            'top_rated_books' => $this
                ->buildTopRatedBooks($reviews),
            'genre_ratings' => $this
                ->buildGenreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * 評価1から5までのレビュー件数を作成する。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧
     * @return Collection<int, int> 評価1から5までのレビュー件数
     */
    private function buildRatingDistribution(
        Collection $reviews
    ): Collection {
        return collect(range(1, 5))
            ->map(
                fn (int $rating): int => $reviews
                    ->where('rating', $rating)
                    ->count()
            );
    }

    /**
     * 評価4以上の書籍を評価順で最大5冊取得する。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧
     * @return Collection<int, array<string, int|string>> 高評価書籍の一覧
     */
    private function buildTopRatedBooks(
        Collection $reviews
    ): Collection {
        return $reviews
            ->filter(
                fn (Review $review): bool => $review->rating >= 4
            )
            ->sortBy([
                ['rating', 'desc'],
                ['created_at', 'desc'],
                ['id', 'desc'],
            ])
            ->take(5)
            ->map(
                fn (Review $review): array => [
                    'id' => $review->book->id,
                    'title' => $review->book->title,
                    'author' => $review->book->author,
                    'rating' => $review->rating,
                ]
            )
            ->values();
    }

    /**
     * ジャンル別の平均評価とレビュー件数を最大5件作成する。
     *
     * @param  Collection<int, Review>  $reviews  集計対象のレビュー一覧
     * @return Collection<int, array<string, float|int|string>> ジャンル別の評価集計
     */
    private function buildGenreRatings(
        Collection $reviews
    ): Collection {
        return $reviews
            ->flatMap(
                fn (Review $review): Collection => $review
                    ->book
                    ->genres
                    ->map(
                        fn (Genre $genre): array => [
                            'id' => $genre->id,
                            'name' => $genre->name,
                            'rating' => $review->rating,
                        ]
                    )
            )
            ->groupBy('id')
            ->map(
                function (Collection $genreReviews): array {
                    $genre = $genreReviews->first();

                    return [
                        'id' => $genre['id'],
                        'name' => $genre['name'],
                        'count' => $genreReviews->count(),
                        'average_rating' => (float) $genreReviews
                            ->avg('rating'),
                    ];
                }
            )
            ->sortBy([
                ['average_rating', 'desc'],
                ['count', 'desc'],
                ['id', 'asc'],
            ])
            ->take(5)
            ->values();
    }
}
