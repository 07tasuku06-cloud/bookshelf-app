<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_belongs_to_user(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();

        $book = $this->createBook($bookOwner);
        $review = $this->createReview($reviewAuthor, $book);

        $this->assertTrue($review->user->is($reviewAuthor));
    }

    public function test_review_belongs_to_book(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();

        $book = $this->createBook($bookOwner);
        $review = $this->createReview($reviewAuthor, $book);

        $this->assertTrue($review->book->is($book));
    }

    public function test_review_belongs_to_many_liked_users(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $book = $this->createBook($bookOwner);
        $review = $this->createReview($reviewAuthor, $book);

        $firstUser->likedReviews()->attach($review->id);
        $secondUser->likedReviews()->attach($review->id);

        $review->load('likedByUsers');

        $this->assertCount(2, $review->likedByUsers);
        $this->assertTrue($review->likedByUsers->contains($firstUser));
        $this->assertTrue($review->likedByUsers->contains($secondUser));
    }

    public function test_rating_is_cast_to_integer(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();

        $book = $this->createBook($bookOwner);
        $review = $this->createReview($reviewAuthor, $book);

        $this->assertIsInt($review->rating);
        $this->assertSame(4, $review->rating);
    }

    private function createBook(User $user): Book
    {
        return Book::create([
            'user_id' => $user->id,
            'title' => 'レビューモデルテスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567892',
            'published_date' => '2026-08-31',
            'description' => 'Reviewモデルのテスト書籍です。',
            'image_url' => null,
        ]);
    }

    private function createReview(User $user, Book $book): Review
    {
        return Review::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'Reviewモデルのテストです。',
        ]);
    }
}
