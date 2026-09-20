<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_book_to_favorites(): void
    {
        $bookOwner = User::factory()->create();
        $user = User::factory()->create();

        $book = $this->createBook($bookOwner, '9784234567897');

        $response = $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'お気に入り状態を更新しました。'
        );

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_authenticated_user_can_remove_book_from_favorites(): void
    {
        $bookOwner = User::factory()->create();
        $user = User::factory()->create();

        $book = $this->createBook($bookOwner, '9785234567896');

        $this
            ->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'お気に入り状態を更新しました。'
        );

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_favorite_index_displays_only_authenticated_users_favorites(): void
    {
        $bookOwner = User::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstBook = $this->createBook(
            $bookOwner,
            '9786234567895',
            '自分のお気に入り書籍1'
        );

        $secondBook = $this->createBook(
            $bookOwner,
            '9787234567894',
            '自分のお気に入り書籍2'
        );

        $otherBook = $this->createBook(
            $bookOwner,
            '9788234567893',
            '他人のお気に入り書籍'
        );

        $this->actingAs($user)
            ->post(route('favorites.toggle', $firstBook));

        $this->actingAs($user)
            ->post(route('favorites.toggle', $secondBook));

        $this->actingAs($otherUser)
            ->post(route('favorites.toggle', $otherBook));

        $response = $this
            ->actingAs($user)
            ->get(route('favorites.index'));

        $response->assertOk();
        $response->assertViewIs('favorites.index');

        $response->assertViewHas('books', function ($books) use (
            $firstBook,
            $secondBook,
            $otherBook
        ) {
            return $books->total() === 2
                && $books->contains('id', $firstBook->id)
                && $books->contains('id', $secondBook->id)
                && ! $books->contains('id', $otherBook->id)
                && $books->every(
                    fn (Book $book) => $book->relationLoaded('genres')
                );
        });
    }

    public function test_favorite_index_is_paginated_by_ten(): void
    {
        $bookOwner = User::factory()->create();
        $user = User::factory()->create();

        for ($index = 1; $index <= 11; $index++) {
            $book = $this->createBook(
                $bookOwner,
                sprintf('9789000000%03d', $index),
                "お気に入り書籍{$index}"
            );

            $this
                ->actingAs($user)
                ->post(route('favorites.toggle', $book));
        }

        $firstPageResponse = $this
            ->actingAs($user)
            ->get(route('favorites.index'));

        $firstPageResponse->assertOk();

        $firstPageResponse->assertViewHas('books', function ($books) {
            return $books->count() === 10
                && $books->total() === 11
                && $books->currentPage() === 1
                && $books->lastPage() === 2;
        });

        $secondPageResponse = $this
            ->actingAs($user)
            ->get(route('favorites.index', ['page' => 2]));

        $secondPageResponse->assertOk();

        $secondPageResponse->assertViewHas('books', function ($books) {
            return $books->count() === 1
                && $books->total() === 11
                && $books->currentPage() === 2;
        });
    }

    public function test_guest_is_redirected_to_login_from_favorite_routes(): void
    {
        $bookOwner = User::factory()->create();
        $book = $this->createBook($bookOwner, '9789334567890');

        $indexResponse = $this->get(route('favorites.index'));

        $indexResponse->assertRedirect(route('login'));

        $toggleResponse = $this->post(route('favorites.toggle', $book));

        $toggleResponse->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
    }

    private function createBook(
        User $owner,
        string $isbn,
        string $title = 'お気に入りテスト書籍'
    ): Book {
        return Book::create([
            'user_id' => $owner->id,
            'title' => $title,
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2026-08-25',
            'description' => 'お気に入り機能のテスト書籍です。',
            'image_url' => null,
        ]);
    }
}
