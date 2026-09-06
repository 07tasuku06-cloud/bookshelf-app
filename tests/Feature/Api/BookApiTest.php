<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private int $isbnSequence = 0;

    public function test_index_can_search_filter_and_paginate_books(): void
    {
        $owner = User::factory()->create();
        $reviewers = User::factory()->count(2)->create();

        $targetGenre = Genre::create([
            'name' => '技術',
        ]);

        $otherGenre = Genre::create([
            'name' => '料理',
        ]);

        $targetBook = $this->createBook($owner, [
            'title' => 'Laravel入門',
            'description' => 'Laravelを学ぶ本です。',
        ]);

        $otherBook = $this->createBook($owner, [
            'title' => '料理入門',
            'description' => '料理を学ぶ本です。',
        ]);

        $secondTargetBook = $this->createBook($owner, [
            'title' => 'Laravel実践',
            'description' => 'Laravelの実践書です。',
        ]);

        $targetBook->genres()->attach($targetGenre->id);
        $otherBook->genres()->attach($otherGenre->id);
        $secondTargetBook->genres()->attach($targetGenre->id);

        Review::create([
            'user_id' => $reviewers[0]->id,
            'book_id' => $targetBook->id,
            'rating' => 4,
            'comment' => '分かりやすいです。',
        ]);

        Review::create([
            'user_id' => $reviewers[1]->id,
            'book_id' => $targetBook->id,
            'rating' => 5,
            'comment' => 'とても参考になりました。',
        ]);

        $response = $this->getJson(
            '/api/v1/books?keyword=Laravel'
                ."&genre_id={$targetGenre->id}"
                .'&per_page=1&page=2'
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $targetBook->id);
        $response->assertJsonPath(
            'data.0.genres.0.id',
            $targetGenre->id
        );
        $response->assertJsonPath('data.0.average_rating', 4.5);
        $response->assertJsonPath('data.0.reviews_count', 2);
        $response->assertJsonPath('meta.per_page', 1);
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'user_id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'description',
                    'image_url',
                    'genres',
                    'average_rating',
                    'reviews_count',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
    }

    public function test_show_returns_book_with_genres_and_reviews(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create([
            'name' => 'レビュー投稿者',
        ]);

        $genre = Genre::create([
            'name' => '小説',
        ]);

        $book = $this->createBook($owner, [
            'title' => 'API詳細テスト',
        ]);

        $book->genres()->attach($genre->id);

        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '詳細APIのレビューです。',
        ]);

        $response = $this->getJson(
            "/api/v1/books/{$book->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $book->id);
        $response->assertJsonPath(
            'data.genres.0.id',
            $genre->id
        );
        $response->assertJsonPath(
            'data.reviews.0.id',
            $review->id
        );
        $response->assertJsonPath(
            'data.reviews.0.user.name',
            'レビュー投稿者'
        );
        $response->assertJsonPath('data.reviews.0.rating', 5);
        $response->assertJsonPath(
            'data.reviews.0.comment',
            '詳細APIのレビューです。'
        );
        $response->assertJsonPath(
            'data.reviews.0.created_at',
            $review->created_at->toISOString()
        );
        $response->assertJsonPath('data.reviews_count', 1);
    }

    public function test_show_returns_json_404_for_missing_book(): void
    {
        $response = $this->getJson('/api/v1/books/999999');

        $response->assertNotFound();
        $response->assertJson([
            'message' => '指定された書籍は存在しません。',
        ]);
    }

    public function test_store_returns_japanese_validation_error(): void
    {
        $genre = Genre::create([
            'name' => 'ビジネス',
        ]);

        $response = $this->postJson('/api/v1/books', [
            'user_id' => 999999,
            'title' => 'API登録テスト',
            'author' => 'API著者',
            'isbn' => '9789999999901',
            'published_date' => '2026-08-19',
            'description' => '登録テストです。',
            'image_url' => null,
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
        $response->assertJsonPath(
            'errors.user_id.0',
            '指定されたユーザーIDは存在しません。'
        );
    }

    public function test_store_creates_book_and_genre_relations(): void
    {
        $owner = User::factory()->create();

        $genres = collect([
            Genre::create(['name' => '技術']),
            Genre::create(['name' => 'プログラミング']),
        ]);

        $response = $this->postJson('/api/v1/books', [
            'user_id' => $owner->id,
            'title' => 'API登録成功テスト',
            'author' => 'API著者',
            'isbn' => '9789999999902',
            'published_date' => '2026-08-19',
            'description' => '登録成功テストです。',
            'image_url' => null,
            'genres' => $genres->pluck('id')->all(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath(
            'data.title',
            'API登録成功テスト'
        );

        $bookId = $response->json('data.id');

        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'user_id' => $owner->id,
            'isbn' => '9789999999902',
        ]);

        foreach ($genres as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $bookId,
                'genre_id' => $genre->id,
            ]);
        }
    }

    public function test_store_allows_missing_isbn_and_published_date(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => 'API任意項目テスト',
        ]);

        $response = $this->postJson('/api/v1/books', [
            'user_id' => $owner->id,
            'title' => 'API任意項目なしの書籍',
            'author' => 'API著者',
            'description' => null,
            'image_url' => null,
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.isbn', null);
        $response->assertJsonPath('data.published_date', null);

        $this->assertDatabaseHas('books', [
            'id' => $response->json('data.id'),
            'user_id' => $owner->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_update_allows_current_isbn_and_syncs_genres(): void
    {
        $owner = User::factory()->create();

        $oldGenre = Genre::create([
            'name' => '変更前ジャンル',
        ]);

        $newGenre = Genre::create([
            'name' => '変更後ジャンル',
        ]);

        $book = $this->createBook($owner, [
            'title' => '変更前タイトル',
            'isbn' => '9789999999903',
        ]);

        $book->genres()->attach($oldGenre->id);

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            [
                'user_id' => $owner->id,
                'title' => '変更後タイトル',
                'author' => '変更後著者',
                'isbn' => '9789999999903',
                'published_date' => '2026-08-20',
                'description' => '更新後の説明です。',
                'image_url' => null,
                'genres' => [$newGenre->id],
            ]
        );

        $response->assertOk();
        $response->assertJsonPath(
            'data.title',
            '変更後タイトル'
        );

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '変更後タイトル',
            'isbn' => '9789999999903',
        ]);

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $oldGenre->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $newGenre->id,
        ]);
    }

    public function test_update_allows_null_isbn_and_published_date(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => 'API NULL更新テスト',
        ]);

        $book = $this->createBook($owner);

        $response = $this->putJson(
            "/api/v1/books/{$book->id}",
            [
                'user_id' => $owner->id,
                'title' => 'API任意項目なしへ更新',
                'author' => 'API更新著者',
                'isbn' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
                'genres' => [$genre->id],
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('data.isbn', null);
        $response->assertJsonPath('data.published_date', null);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'API任意項目なしへ更新',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_update_and_destroy_return_json_404_for_missing_book(): void
    {
        $updateResponse = $this->putJson(
            '/api/v1/books/999999',
            []
        );

        $updateResponse->assertNotFound();
        $updateResponse->assertJson([
            'message' => '指定された書籍は存在しません。',
        ]);

        $deleteResponse = $this->deleteJson(
            '/api/v1/books/999999'
        );

        $deleteResponse->assertNotFound();
        $deleteResponse->assertJson([
            'message' => '指定された書籍は存在しません。',
        ]);
    }

    public function test_destroy_deletes_book_and_related_data(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除テスト',
        ]);

        $book = $this->createBook($owner);

        $book->genres()->attach($genre->id);

        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '削除されるレビューです。',
        ]);

        DB::table('favorites')->insert([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson(
            "/api/v1/books/{$book->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $this->assertDatabaseMissing('favorites', [
            'book_id' => $book->id,
        ]);

        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
        ]);
    }

    private function createBook(
        User $owner,
        array $attributes = []
    ): Book {
        $this->isbnSequence++;

        return Book::create(array_merge([
            'user_id' => $owner->id,
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => sprintf(
                '978%010d',
                $this->isbnSequence
            ),
            'published_date' => '2026-08-19',
            'description' => 'テスト用の説明です。',
            'image_url' => null,
        ], $attributes));
    }
}
