<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_report(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_report_displays_zero_values_when_user_has_no_data(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewIs('reports.index');

        /** @var array<string, mixed> $stats */
        $stats = $response->viewData('stats');

        $this->assertSame([
            'total_reviews' => 0,
            'books_read' => 0,
            'average_rating' => 0.0,
        ], $stats['summary']);

        $this->assertSame(
            [0, 0, 0, 0, 0],
            $stats['rating_distribution']->all()
        );

        $this->assertTrue(
            $stats['top_rated_books']->isEmpty()
        );
        $this->assertTrue(
            $stats['genre_ratings']->isEmpty()
        );
    }

    public function test_summary_and_rating_distribution_use_only_authenticated_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstBook = $this->createBook(
            $user,
            '評価5の書籍'
        );
        $secondBook = $this->createBook(
            $user,
            '評価3の書籍'
        );
        $thirdBook = $this->createBook(
            $user,
            '評価1の書籍'
        );
        $otherBook = $this->createBook(
            $otherUser,
            '他人の書籍'
        );

        $this->createReview($user, $firstBook, 5);
        $this->createReview($user, $secondBook, 3);
        $this->createReview($user, $thirdBook, 1);
        $this->createReview($otherUser, $otherBook, 5);

        $this->createReadingPlan(
            $user,
            $firstBook,
            ReadingPlanStatus::Completed
        );
        $this->createReadingPlan(
            $user,
            $firstBook,
            ReadingPlanStatus::Completed
        );
        $this->createReadingPlan(
            $user,
            $secondBook,
            ReadingPlanStatus::Completed
        );
        $this->createReadingPlan(
            $user,
            $thirdBook,
            ReadingPlanStatus::Planned
        );
        $this->createReadingPlan(
            $otherUser,
            $otherBook,
            ReadingPlanStatus::Completed
        );

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertOk();

        /** @var array<string, mixed> $stats */
        $stats = $response->viewData('stats');

        $this->assertSame(
            3,
            $stats['summary']['total_reviews']
        );
        $this->assertSame(
            2,
            $stats['summary']['books_read']
        );
        $this->assertSame(
            3.0,
            $stats['summary']['average_rating']
        );
        $this->assertSame(
            [1, 0, 1, 0, 1],
            $stats['rating_distribution']->all()
        );

        $this->assertFalse(
            $stats['top_rated_books']
                ->contains('title', '他人の書籍')
        );
    }

    public function test_top_rated_books_contains_at_most_five_books_rated_four_or_higher(): void
    {
        $user = User::factory()->create();

        collect([
            ['評価5・一冊目', 5],
            ['評価4・一冊目', 4],
            ['評価5・二冊目', 5],
            ['評価4・二冊目', 4],
            ['評価5・三冊目', 5],
            ['評価4・三冊目', 4],
            ['評価3・対象外', 3],
        ])->each(
            function (array $data) use ($user): void {
                $book = $this->createBook(
                    $user,
                    $data[0]
                );

                $this->createReview(
                    $user,
                    $book,
                    $data[1]
                );
            }
        );

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertOk();

        /** @var array<string, mixed> $stats */
        $stats = $response->viewData('stats');

        $topRatedBooks = $stats['top_rated_books'];

        $this->assertCount(5, $topRatedBooks);
        $this->assertSame(
            [5, 5, 5, 4, 4],
            $topRatedBooks->pluck('rating')->all()
        );
        $this->assertFalse(
            $topRatedBooks->contains(
                'title',
                '評価3・対象外'
            )
        );
    }

    public function test_genre_ratings_are_grouped_and_sorted_using_only_users_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $technology = Genre::create([
            'name' => '技術',
        ]);
        $business = Genre::create([
            'name' => 'ビジネス',
        ]);
        $novel = Genre::create([
            'name' => '小説',
        ]);

        $firstBook = $this->createBook(
            $user,
            '技術・ビジネス書籍',
            [$technology, $business]
        );
        $secondBook = $this->createBook(
            $user,
            '技術書籍',
            [$technology]
        );
        $thirdBook = $this->createBook(
            $user,
            'ビジネス書籍',
            [$business]
        );
        $fourthBook = $this->createBook(
            $user,
            '小説書籍',
            [$novel]
        );

        $this->createReview($user, $firstBook, 5);
        $this->createReview($user, $secondBook, 3);
        $this->createReview($user, $thirdBook, 4);
        $this->createReview($user, $fourthBook, 5);

        $this->createReview(
            $otherUser,
            $firstBook,
            1
        );

        $response = $this
            ->actingAs($user)
            ->get(route('reports.index'));

        $response->assertOk();

        /** @var array<string, mixed> $stats */
        $stats = $response->viewData('stats');

        $genreRatings = $stats['genre_ratings'];

        $this->assertSame([
            '小説',
            'ビジネス',
            '技術',
        ], $genreRatings->pluck('name')->all());

        $novelStats = $genreRatings->firstWhere(
            'name',
            '小説'
        );
        $businessStats = $genreRatings->firstWhere(
            'name',
            'ビジネス'
        );
        $technologyStats = $genreRatings->firstWhere(
            'name',
            '技術'
        );

        $this->assertSame(1, $novelStats['count']);
        $this->assertSame(
            5.0,
            $novelStats['average_rating']
        );

        $this->assertSame(2, $businessStats['count']);
        $this->assertSame(
            4.5,
            $businessStats['average_rating']
        );

        $this->assertSame(2, $technologyStats['count']);
        $this->assertSame(
            4.0,
            $technologyStats['average_rating']
        );
    }

    /**
     * 指定したジャンルを持つテスト用書籍を作成する。
     *
     * @param  array<int, Genre>  $genres
     */
    private function createBook(
        User $user,
        string $title,
        array $genres = []
    ): Book {
        $book = Book::create([
            'user_id' => $user->id,
            'title' => $title,
            'author' => 'レポートテスト著者',
        ]);

        $book->genres()->sync(
            collect($genres)
                ->map(
                    fn (Genre $genre): int => $genre->id
                )
                ->all()
        );

        return $book;
    }

    /**
     * 指定した評価でテスト用レビューを作成する。
     */
    private function createReview(
        User $user,
        Book $book,
        int $rating
    ): Review {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => $rating,
            'comment' => 'レポート機能のテストです。',
        ]);
    }

    /**
     * 指定した状態でテスト用読書計画を作成する。
     */
    private function createReadingPlan(
        User $user,
        Book $book,
        ReadingPlanStatus $status
    ): ReadingPlan {
        return ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-09-30',
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed
                ? '2026-09-08 12:00:00'
                : null,
        ]);
    }
}
