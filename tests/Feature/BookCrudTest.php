<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_book_with_genres(): void
    {
        $user = User::factory()->create();

        $firstGenre = Genre::create([
            'name' => '技術',
        ]);

        $secondGenre = Genre::create([
            'name' => 'プログラミング',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('books.store'), [
                'title' => 'Laravelテスト入門',
                'author' => 'テスト著者',
                'isbn' => '9781234567890',
                'published_date' => '2026-08-25',
                'description' => '書籍登録テストです。',
                'image_url' => 'https://example.com/book.jpg',
                'genres' => [
                    $firstGenre->id,
                    $secondGenre->id,
                ],
            ]);

        $book = Book::where('isbn', '9781234567890')->firstOrFail();

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            '書籍を登録しました。'
        );

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'title' => 'Laravelテスト入門',
            'isbn' => '9781234567890',
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $firstGenre->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $secondGenre->id,
        ]);
    }

    public function test_authenticated_user_can_create_book_without_isbn_and_published_date(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '任意項目テスト',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('books.store'), [
                'title' => '任意項目なしの書籍',
                'author' => 'テスト著者',
                'isbn' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
                'genres' => [$genre->id],
            ]);

        $book = Book::where('title', '任意項目なしの書籍')->firstOrFail();

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_book_creation_returns_japanese_validation_messages(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => '',
                'author' => '',
                'isbn' => '123',
                'published_date' => 'invalid-date',
                'description' => 'テスト説明',
                'image_url' => 'invalid-url',
                'genres' => [],
            ]);

        $response->assertRedirect(route('books.create'));

        $response->assertSessionHasErrors([
            'title' => 'タイトルを入力してください。',
            'author' => '著者名を入力してください。',
            'isbn' => 'ISBNは13桁で入力してください。',
            'published_date' => '出版日は有効な日付を入力してください。',
            'image_url' => '画像URLは有効なURL形式で入力してください。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_owner_can_update_book_and_replace_genres(): void
    {
        $owner = User::factory()->create();

        $oldGenre = Genre::create([
            'name' => '変更前ジャンル',
        ]);

        $newGenre = Genre::create([
            'name' => '変更後ジャンル',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '変更前タイトル',
            'author' => '変更前著者',
            'isbn' => '9781234567891',
            'published_date' => '2026-08-24',
            'description' => '変更前の説明です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($oldGenre->id);

        $response = $this
            ->actingAs($owner)
            ->put(route('books.update', $book), [
                'title' => '変更後タイトル',
                'author' => '変更後著者',
                'isbn' => '9781234567891',
                'published_date' => '2026-08-25',
                'description' => '変更後の説明です。',
                'image_url' => null,
                'genres' => [
                    $newGenre->id,
                ],
            ]);

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            '書籍を更新しました。'
        );

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '変更後タイトル',
            'author' => '変更後著者',
            'isbn' => '9781234567891',
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

    public function test_owner_can_update_book_without_isbn_and_published_date(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => 'NULL更新テスト',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '更新前タイトル',
            'author' => '更新前著者',
            'isbn' => '9781234567899',
            'published_date' => '2026-08-24',
            'description' => null,
            'image_url' => null,
        ]);

        $book->genres()->attach($genre->id);

        $response = $this
            ->actingAs($owner)
            ->put(route('books.update', $book), [
                'title' => '任意項目なしへ更新',
                'author' => '更新後著者',
                'isbn' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
                'genres' => [$genre->id],
            ]);

        $response->assertRedirect(route('books.show', $book));

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '任意項目なしへ更新',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    public function test_book_update_returns_japanese_validation_messages(): void
    {
        $owner = User::factory()->create();

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '更新前タイトル',
            'author' => '更新前著者',
            'isbn' => '9781234567892',
            'published_date' => '2026-08-24',
            'description' => '更新前の説明です。',
            'image_url' => null,
        ]);

        $response = $this
            ->actingAs($owner)
            ->from(route('books.edit', $book))
            ->put(route('books.update', $book), [
                'title' => '',
                'author' => '',
                'isbn' => '123',
                'published_date' => 'invalid-date',
                'description' => 'テスト説明',
                'image_url' => 'invalid-url',
                'genres' => [],
            ]);

        $response->assertRedirect(route('books.edit', $book));

        $response->assertSessionHasErrors([
            'title' => 'タイトルを入力してください。',
            'author' => '著者名を入力してください。',
            'isbn' => 'ISBNは13桁で入力してください。',
            'published_date' => '出版日は有効な日付を入力してください。',
            'image_url' => '画像URLは有効なURL形式で入力してください。',
            'genres' => 'ジャンルを1つ以上選択してください。',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新前タイトル',
            'isbn' => '9781234567892',
        ]);
    }

    public function test_non_owner_cannot_edit_update_or_delete_book(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $genre = Genre::create([
            'name' => '認可テスト',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '所有者の書籍',
            'author' => '所有者',
            'isbn' => '9781234567893',
            'published_date' => '2026-08-25',
            'description' => '認可テスト用の書籍です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($genre->id);

        $editResponse = $this
            ->actingAs($otherUser)
            ->get(route('books.edit', $book));

        $editResponse->assertForbidden();

        $updateResponse = $this
            ->actingAs($otherUser)
            ->put(route('books.update', $book), [
                'title' => '不正に変更されたタイトル',
                'author' => '別のユーザー',
                'isbn' => '9781234567893',
                'published_date' => '2026-08-25',
                'description' => '不正な更新です。',
                'image_url' => null,
                'genres' => [
                    $genre->id,
                ],
            ]);

        $updateResponse->assertForbidden();

        $deleteResponse = $this
            ->actingAs($otherUser)
            ->delete(route('books.destroy', $book));

        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $owner->id,
            'title' => '所有者の書籍',
        ]);
    }

    public function test_owner_can_delete_book_and_related_data(): void
    {
        $owner = User::factory()->create();
        $relatedUser = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除テスト',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '削除対象の書籍',
            'author' => '削除テスト著者',
            'isbn' => '9781234567894',
            'published_date' => '2026-08-25',
            'description' => '削除テスト用の書籍です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($genre->id);

        $review = Review::create([
            'user_id' => $relatedUser->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '削除対象のレビューです。',
        ]);

        $relatedUser->favoriteBooks()->attach($book->id);
        $relatedUser->likedReviews()->attach($review->id);

        $response = $this
            ->actingAs($owner)
            ->delete(route('books.destroy', $book));

        $response->assertRedirect(route('books.index'));

        $response->assertSessionHas(
            'success',
            '書籍を削除しました。'
        );

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

        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);
    }

    public function test_book_creation_rejects_duplicate_isbn(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => 'ISBNテスト',
        ]);

        Book::create([
            'user_id' => $user->id,
            'title' => '登録済み書籍',
            'author' => '登録済み著者',
            'isbn' => '9781234567895',
            'published_date' => '2026-08-25',
            'description' => '先に登録された書籍です。',
            'image_url' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => '重複登録する書籍',
                'author' => '別の著者',
                'isbn' => '9781234567895',
                'published_date' => '2026-08-25',
                'description' => '同じISBNで登録を試します。',
                'image_url' => null,
                'genres' => [
                    $genre->id,
                ],
            ]);

        $response->assertRedirect(route('books.create'));

        $response->assertSessionHasErrors([
            'isbn' => 'このISBNはすでに登録されています。',
        ]);

        $this->assertDatabaseCount('books', 1);

        $this->assertDatabaseMissing('books', [
            'title' => '重複登録する書籍',
        ]);
    }

    public function test_book_update_rejects_isbn_used_by_another_book(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => '更新ISBNテスト',
        ]);

        Book::create([
            'user_id' => $owner->id,
            'title' => '別の書籍',
            'author' => '別の著者',
            'isbn' => '9781234567896',
            'published_date' => '2026-08-24',
            'description' => 'ISBNを使用済みの書籍です。',
            'image_url' => null,
        ]);

        $targetBook = Book::create([
            'user_id' => $owner->id,
            'title' => '更新対象の書籍',
            'author' => '更新対象の著者',
            'isbn' => '9781234567897',
            'published_date' => '2026-08-25',
            'description' => '更新対象です。',
            'image_url' => null,
        ]);

        $response = $this
            ->actingAs($owner)
            ->from(route('books.edit', $targetBook))
            ->put(route('books.update', $targetBook), [
                'title' => '更新後タイトル',
                'author' => '更新後著者',
                'isbn' => '9781234567896',
                'published_date' => '2026-08-25',
                'description' => '重複ISBNで更新します。',
                'image_url' => null,
                'genres' => [
                    $genre->id,
                ],
            ]);

        $response->assertRedirect(route('books.edit', $targetBook));

        $response->assertSessionHasErrors([
            'isbn' => 'このISBNはすでに登録されています。',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $targetBook->id,
            'title' => '更新対象の書籍',
            'isbn' => '9781234567897',
        ]);
    }

    public function test_book_creation_rejects_nonexistent_genre(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('books.create'))
            ->post(route('books.store'), [
                'title' => '存在しないジャンルの書籍',
                'author' => 'テスト著者',
                'isbn' => '9781234567898',
                'published_date' => '2026-08-25',
                'description' => '存在しないジャンルIDを送信します。',
                'image_url' => null,
                'genres' => [
                    999999,
                ],
            ]);

        $response->assertRedirect(route('books.create'));

        $response->assertSessionHasErrors([
            'genres.0' => '選択されたジャンルは存在しません。',
        ]);

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_genre', 0);
    }

    public function test_guest_is_redirected_to_login_when_attempting_book_writes(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => 'ゲスト操作テスト',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '既存の書籍',
            'author' => '既存の著者',
            'isbn' => '9781234567899',
            'published_date' => '2026-08-25',
            'description' => 'ゲスト操作の対象です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($genre->id);

        $storeResponse = $this->post(route('books.store'), [
            'title' => 'ゲストが登録する書籍',
            'author' => 'ゲスト',
            'isbn' => '9781234567900',
            'published_date' => '2026-08-25',
            'description' => '登録されてはいけません。',
            'image_url' => null,
            'genres' => [
                $genre->id,
            ],
        ]);

        $storeResponse->assertRedirect(route('login'));

        $updateResponse = $this->put(route('books.update', $book), [
            'title' => 'ゲストによる更新',
            'author' => 'ゲスト',
            'isbn' => '9781234567899',
            'published_date' => '2026-08-25',
            'description' => '更新されてはいけません。',
            'image_url' => null,
            'genres' => [
                $genre->id,
            ],
        ]);

        $updateResponse->assertRedirect(route('login'));

        $deleteResponse = $this->delete(route('books.destroy', $book));

        $deleteResponse->assertRedirect(route('login'));

        $this->assertDatabaseMissing('books', [
            'title' => 'ゲストが登録する書籍',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '既存の書籍',
        ]);
    }

    public function test_owner_can_view_edit_page_with_book_and_genres(): void
    {
        $owner = User::factory()->create();

        $selectedGenre = Genre::create([
            'name' => '選択済みジャンル',
        ]);

        $otherGenre = Genre::create([
            'name' => '未選択ジャンル',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '編集画面テスト書籍',
            'author' => '編集画面テスト著者',
            'isbn' => '9781234567901',
            'published_date' => '2026-08-25',
            'description' => '編集画面へ渡される書籍です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($selectedGenre->id);

        $response = $this
            ->actingAs($owner)
            ->get(route('books.edit', $book));

        $response->assertOk();
        $response->assertViewIs('books.edit');

        $response->assertViewHas('book', function (Book $viewBook) use ($book, $selectedGenre) {
            return $viewBook->is($book)
                && $viewBook->relationLoaded('genres')
                && $viewBook->genres->contains('id', $selectedGenre->id);
        });

        $response->assertViewHas('genres', function ($genres) use (
            $selectedGenre,
            $otherGenre
        ) {
            return $genres->pluck('id')->all() === [
                $otherGenre->id,
                $selectedGenre->id,
            ];
        });

        $response->assertSee('編集画面テスト書籍');
        $response->assertSee('編集画面テスト著者');
        $response->assertSee('9781234567901');
        $response->assertSee('value="2026-08-25"', false);
    }

    public function test_book_index_is_paginated_by_ten_in_latest_order_with_genres(): void
    {
        $owner = User::factory()->create();

        $genre = Genre::create([
            'name' => '一覧テストジャンル',
        ]);

        for ($number = 1; $number <= 11; $number++) {
            $book = Book::create([
                'user_id' => $owner->id,
                'title' => "一覧テスト書籍{$number}",
                'author' => '一覧テスト著者',
                'isbn' => sprintf('978%010d', $number),
                'published_date' => '2026-08-25',
                'description' => '一覧表示のテストです。',
                'image_url' => null,
            ]);

            $book->created_at = now()
                ->startOfDay()
                ->addMinutes($number);

            $book->save();

            $book->genres()->attach($genre->id);
        }

        $response = $this->get(route('books.index'));

        $response->assertOk();
        $response->assertViewIs('books.index');

        $response->assertViewHas('books', function ($books) use ($genre) {
            $displayedBooks = $books->getCollection();

            return $books->count() === 10
                && $books->total() === 11
                && $displayedBooks->first()->title === '一覧テスト書籍11'
                && $displayedBooks->every(function (Book $book) use ($genre) {
                    return $book->relationLoaded('genres')
                        && $book->genres->contains('id', $genre->id);
                });
        });

        $response->assertSee('一覧テストジャンル');
    }

    public function test_book_detail_displays_book_genres_reviews_and_like_count(): void
    {
        $owner = User::factory()->create();

        $reviewer = User::factory()->create([
            'name' => '詳細テスト投稿者',
        ]);

        $liker = User::factory()->create();

        $genre = Genre::create([
            'name' => '詳細テストジャンル',
        ]);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '詳細表示対象の書籍',
            'author' => '詳細表示テスト著者',
            'isbn' => '9781234567902',
            'published_date' => '2026-08-25',
            'description' => '詳細画面に表示する説明です。',
            'image_url' => 'https://example.com/detail-book.jpg',
        ]);

        $book->genres()->attach($genre->id);

        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '詳細画面に表示するレビューです。',
        ]);

        $liker->likedReviews()->attach($review->id);

        $response = $this->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewIs('books.show');

        $response->assertViewHas('book', function (Book $viewBook) {
            return $viewBook->relationLoaded('genres')
                && $viewBook->relationLoaded('reviews')
                && $viewBook->reviews->every(function (Review $review) {
                    return $review->relationLoaded('user')
                        && $review->relationLoaded('likedByUsers');
                });
        });

        $response->assertSee('詳細表示対象の書籍');
        $response->assertSee('詳細表示テスト著者');
        $response->assertSee('9781234567902');
        $response->assertSee('2026-08-25');
        $response->assertSee('詳細画面に表示する説明です。');
        $response->assertSee('詳細テストジャンル');
        $response->assertSee('詳細テスト投稿者');
        $response->assertSee('★★★★☆');
        $response->assertSee('詳細画面に表示するレビューです。');
        $response->assertSee('いいね (1)');
    }
}
