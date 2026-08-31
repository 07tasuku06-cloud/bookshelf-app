<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_books(): void
    {
        $user = User::factory()->create();

        $firstBook = $this->createBook(
            $user,
            'ユーザーテスト書籍1',
            '9781234567893'
        );

        $secondBook = $this->createBook(
            $user,
            'ユーザーテスト書籍2',
            '9781234567894'
        );

        $user->load('books');

        $this->assertCount(2, $user->books);
        $this->assertTrue($user->books->contains($firstBook));
        $this->assertTrue($user->books->contains($secondBook));
    }

    public function test_user_has_many_reviews(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();

        $firstBook = $this->createBook(
            $bookOwner,
            'レビューテスト書籍1',
            '9781234567895'
        );

        $secondBook = $this->createBook(
            $bookOwner,
            'レビューテスト書籍2',
            '9781234567896'
        );

        $firstReview = Review::create([
            'user_id' => $reviewAuthor->id,
            'book_id' => $firstBook->id,
            'rating' => 4,
            'comment' => '1件目のレビューです。',
        ]);

        $secondReview = Review::create([
            'user_id' => $reviewAuthor->id,
            'book_id' => $secondBook->id,
            'rating' => 5,
            'comment' => '2件目のレビューです。',
        ]);

        $reviewAuthor->load('reviews');

        $this->assertCount(2, $reviewAuthor->reviews);
        $this->assertTrue($reviewAuthor->reviews->contains($firstReview));
        $this->assertTrue($reviewAuthor->reviews->contains($secondReview));
    }

    public function test_user_belongs_to_many_favorite_books(): void
    {
        $bookOwner = User::factory()->create();
        $user = User::factory()->create();

        $firstBook = $this->createBook(
            $bookOwner,
            'お気に入りテスト書籍1',
            '9781234567897'
        );

        $secondBook = $this->createBook(
            $bookOwner,
            'お気に入りテスト書籍2',
            '9781234567898'
        );

        $user->favoriteBooks()->attach([
            $firstBook->id,
            $secondBook->id,
        ]);

        $user->load('favoriteBooks');

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->contains($firstBook));
        $this->assertTrue($user->favoriteBooks->contains($secondBook));
    }

    public function test_user_belongs_to_many_liked_reviews(): void
    {
        $bookOwner = User::factory()->create();
        $firstReviewAuthor = User::factory()->create();
        $secondReviewAuthor = User::factory()->create();
        $user = User::factory()->create();

        $book = $this->createBook(
            $bookOwner,
            'レビューいいねテスト書籍',
            '9781234567899'
        );

        $firstReview = Review::create([
            'user_id' => $firstReviewAuthor->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '1件目のレビューです。',
        ]);

        $secondReview = Review::create([
            'user_id' => $secondReviewAuthor->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '2件目のレビューです。',
        ]);

        $user->likedReviews()->attach([
            $firstReview->id,
            $secondReview->id,
        ]);

        $user->load('likedReviews');

        $this->assertCount(2, $user->likedReviews);
        $this->assertTrue($user->likedReviews->contains($firstReview));
        $this->assertTrue($user->likedReviews->contains($secondReview));
    }

    private function createBook(
        User $user,
        string $title,
        string $isbn
    ): Book {
        return Book::create([
            'user_id' => $user->id,
            'title' => $title,
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2026-08-31',
            'description' => 'Userモデルのテスト書籍です。',
            'image_url' => null,
        ]);
    }
}
