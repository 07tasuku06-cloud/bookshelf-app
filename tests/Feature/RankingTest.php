<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_ranking_page(): void
    {
        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewIs('ranking.index');
        $response->assertViewHas('rankedBooks');
    }

    public function test_books_without_reviews_are_excluded_from_ranking(): void
    {
        $user = User::factory()->create();

        $reviewedBook = Book::create([
            'user_id' => $user->id,
            'title' => 'レビューありの書籍',
            'author' => '著者A',
            'isbn' => '1234567890123',
            'published_date' => '2026-01-01',
        ]);

        $bookWithoutReviews = Book::create([
            'user_id' => $user->id,
            'title' => 'レビューなしの書籍',
            'author' => '著者B',
            'isbn' => '1234567890124',
            'published_date' => '2026-01-02',
        ]);

        Review::create([
            'user_id' => $user->id,
            'book_id' => $reviewedBook->id,
            'rating' => 5,
            'comment' => 'テストレビュー',
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas(
            'rankedBooks',
            function ($rankedBooks) use (
                $reviewedBook,
                $bookWithoutReviews
            ) {
                return $rankedBooks
                    ->pluck('id')
                    ->contains($reviewedBook->id)
                    && ! $rankedBooks
                        ->pluck('id')
                        ->contains($bookWithoutReviews->id);
            }
        );
    }

    public function test_books_are_ranked_by_average_rating_review_count_and_id(): void
    {
        $owner = User::factory()->create();
        $reviewers = User::factory()->count(3)->create();

        $lowerIdBook = Book::create([
            'user_id' => $owner->id,
            'title' => 'IDが小さい書籍',
            'author' => '著者A',
            'isbn' => '1234567890201',
            'published_date' => '2026-01-01',
        ]);

        $higherIdBook = Book::create([
            'user_id' => $owner->id,
            'title' => 'IDが大きい書籍',
            'author' => '著者B',
            'isbn' => '1234567890202',
            'published_date' => '2026-01-02',
        ]);

        $moreReviewsBook = Book::create([
            'user_id' => $owner->id,
            'title' => 'レビュー件数が多い書籍',
            'author' => '著者C',
            'isbn' => '1234567890203',
            'published_date' => '2026-01-03',
        ]);

        $highestRatedBook = Book::create([
            'user_id' => $owner->id,
            'title' => '平均評価が高い書籍',
            'author' => '著者D',
            'isbn' => '1234567890204',
            'published_date' => '2026-01-04',
        ]);

        Review::create([
            'user_id' => $reviewers[0]->id,
            'book_id' => $lowerIdBook->id,
            'rating' => 4,
            'comment' => '評価4',
        ]);

        Review::create([
            'user_id' => $reviewers[0]->id,
            'book_id' => $higherIdBook->id,
            'rating' => 4,
            'comment' => '評価4',
        ]);

        Review::create([
            'user_id' => $reviewers[0]->id,
            'book_id' => $moreReviewsBook->id,
            'rating' => 3,
            'comment' => '評価3',
        ]);

        Review::create([
            'user_id' => $reviewers[1]->id,
            'book_id' => $moreReviewsBook->id,
            'rating' => 5,
            'comment' => '評価5',
        ]);

        Review::create([
            'user_id' => $reviewers[2]->id,
            'book_id' => $highestRatedBook->id,
            'rating' => 5,
            'comment' => '評価5',
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas(
            'rankedBooks',
            function ($rankedBooks) use (
                $highestRatedBook,
                $moreReviewsBook,
                $lowerIdBook,
                $higherIdBook
            ) {
                return $rankedBooks
                    ->pluck('id')
                    ->values()
                    ->all() === [
                        $highestRatedBook->id,
                        $moreReviewsBook->id,
                        $lowerIdBook->id,
                        $higherIdBook->id,
                    ];
            }
        );
    }

    public function test_ranking_displays_only_top_ten_books(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $books = collect();

        for ($number = 1; $number <= 11; $number++) {
            $book = Book::create([
                'user_id' => $owner->id,
                'title' => "テスト書籍{$number}",
                'author' => 'テスト著者',
                'isbn' => '9780000000' . str_pad(
                    (string) $number,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'published_date' => '2026-01-01',
            ]);

            Review::create([
                'user_id' => $reviewer->id,
                'book_id' => $book->id,
                'rating' => 5,
                'comment' => 'テストレビュー',
            ]);

            $books->push($book);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertOk();
        $response->assertViewHas(
            'rankedBooks',
            function ($rankedBooks) use ($books) {
                return $rankedBooks->count() === 10
                    && $rankedBooks->pluck('id')->all()
                    === $books->take(10)->pluck('id')->all();
            }
        );
    }
}
