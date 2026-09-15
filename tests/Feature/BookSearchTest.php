<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $isbnSequence = 0;

    public function test_keyword_searches_book_title_and_author(): void
    {
        $owner = User::factory()->create();

        $titleMatch = $this->createBook(
            $owner,
            'Laravel入門',
            '山田太郎'
        );

        $authorMatch = $this->createBook(
            $owner,
            'PHP入門',
            'Laravel研究会'
        );

        $this->createBook(
            $owner,
            'データベース入門',
            '鈴木花子'
        );

        $response = $this->get(route('books.index', [
            'keyword' => 'Laravel',
        ]));

        $response->assertOk();

        $this->assertEqualsCanonicalizing(
            [$titleMatch->id, $authorMatch->id],
            $this->displayedBookIds($response)
        );
    }

    public function test_genre_filter_displays_only_related_books(): void
    {
        $owner = User::factory()->create();

        $technicalGenre = Genre::create([
            'name' => '技術書',
        ]);

        $novelGenre = Genre::create([
            'name' => '小説',
        ]);

        $technicalBook = $this->createBook(
            $owner,
            '技術書籍',
            '技術著者'
        );

        $novelBook = $this->createBook(
            $owner,
            '小説書籍',
            '小説著者'
        );

        $technicalBook->genres()->attach($technicalGenre);
        $novelBook->genres()->attach($novelGenre);

        $response = $this->get(route('books.index', [
            'genre' => $technicalGenre->id,
        ]));

        $response->assertOk();

        $this->assertSame(
            [$technicalBook->id],
            $this->displayedBookIds($response)
        );
    }

    public function test_books_can_be_sorted_by_each_supported_option(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        $alpha = $this->createBook(
            $owner,
            'Alpha',
            '著者A',
            now()->subDays(3)
        );

        $bravo = $this->createBook(
            $owner,
            'Bravo',
            '著者B',
            now()->subDays(2)
        );

        $charlie = $this->createBook(
            $owner,
            'Charlie',
            '著者C',
            now()->subDay()
        );

        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $alpha->id,
            'rating' => 3,
            'comment' => '評価3',
        ]);

        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $bravo->id,
            'rating' => 5,
            'comment' => '評価5',
        ]);

        Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $charlie->id,
            'rating' => 4,
            'comment' => '評価4',
        ]);

        $newestResponse = $this->get(route('books.index', [
            'sort' => 'newest',
        ]));

        $oldestResponse = $this->get(route('books.index', [
            'sort' => 'oldest',
        ]));

        $ratingResponse = $this->get(route('books.index', [
            'sort' => 'rating',
        ]));

        $titleResponse = $this->get(route('books.index', [
            'sort' => 'title',
        ]));

        $this->assertSame(
            [$charlie->id, $bravo->id, $alpha->id],
            $this->displayedBookIds($newestResponse)
        );

        $this->assertSame(
            [$alpha->id, $bravo->id, $charlie->id],
            $this->displayedBookIds($oldestResponse)
        );

        $this->assertSame(
            [$bravo->id, $charlie->id, $alpha->id],
            $this->displayedBookIds($ratingResponse)
        );

        $this->assertSame(
            [$alpha->id, $bravo->id, $charlie->id],
            $this->displayedBookIds($titleResponse)
        );
    }

    public function test_keyword_and_genre_conditions_can_be_combined(): void
    {
        $owner = User::factory()->create();

        $technicalGenre = Genre::create([
            'name' => '技術書',
        ]);

        $novelGenre = Genre::create([
            'name' => '小説',
        ]);

        $matchingBook = $this->createBook(
            $owner,
            'Laravel実践',
            '技術著者'
        );

        $differentGenreBook = $this->createBook(
            $owner,
            'Laravel小説',
            '小説著者'
        );

        $differentKeywordBook = $this->createBook(
            $owner,
            'PHP実践',
            '技術著者'
        );

        $matchingBook->genres()->attach($technicalGenre);
        $differentGenreBook->genres()->attach($novelGenre);
        $differentKeywordBook->genres()->attach($technicalGenre);

        $response = $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $technicalGenre->id,
        ]));

        $response->assertOk();

        $this->assertSame(
            [$matchingBook->id],
            $this->displayedBookIds($response)
        );
    }

    public function test_invalid_search_conditions_are_rejected(): void
    {
        $response = $this
            ->from(route('books.index'))
            ->get(route('books.index', [
                'genre' => 999999,
                'sort' => 'invalid',
            ]));

        $response->assertRedirect(route('books.index'));

        $response->assertSessionHasErrors([
            'genre' => '選択されたジャンルは存在しません。',
            'sort' => '並び順の指定が正しくありません。',
        ]);
    }

    public function test_pagination_links_keep_search_conditions(): void
    {
        $owner = User::factory()->create();

        foreach (range(1, 11) as $number) {
            $this->createBook(
                $owner,
                "Pagination {$number}",
                'ページ著者'
            );
        }

        $response = $this->get(route('books.index', [
            'keyword' => 'Pagination',
            'sort' => 'oldest',
        ]));

        $response->assertOk();

        $books = $response->viewData('books');

        $this->assertInstanceOf(
            LengthAwarePaginator::class,
            $books
        );

        $this->assertSame(11, $books->total());
        $this->assertStringContainsString(
            'keyword=Pagination',
            $books->url(2)
        );
        $this->assertStringContainsString(
            'sort=oldest',
            $books->url(2)
        );
    }

    /**
     * テスト用書籍を作成する。
     */
    private function createBook(
        User $owner,
        string $title,
        string $author,
        ?Carbon $createdAt = null
    ): Book {
        $this->isbnSequence++;

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => $title,
            'author' => $author,
            'isbn' => sprintf(
                '978%010d',
                $this->isbnSequence
            ),
            'published_date' => null,
            'description' => null,
            'image_url' => null,
        ]);

        if ($createdAt !== null) {
            $book->created_at = $createdAt;
            $book->updated_at = $createdAt;
            $book->saveQuietly();
        }

        return $book;
    }

    /**
     * 一覧に表示された書籍IDを返す。
     *
     * @return array<int, int>
     */
    private function displayedBookIds(TestResponse $response): array
    {
        $books = $response->viewData('books');

        $this->assertInstanceOf(
            LengthAwarePaginator::class,
            $books
        );

        return $books
            ->getCollection()
            ->pluck('id')
            ->all();
    }
}
