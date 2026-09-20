<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_like_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();
        $user = User::factory()->create();

        $book = $this->createBook($bookOwner, '9789434567899');
        $review = $this->createReview($reviewAuthor, $book);

        $response = $this
            ->actingAs($user)
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'レビューのいいね状態を更新しました。'
        );

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_authenticated_user_can_unlike_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();
        $user = User::factory()->create();

        $book = $this->createBook($bookOwner, '9789534567898');
        $review = $this->createReview($reviewAuthor, $book);

        $this
            ->actingAs($user)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        $response = $this
            ->post(route('reviews.like', $review));

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'レビューのいいね状態を更新しました。'
        );

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    public function test_review_likes_are_managed_independently_for_each_user(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $book = $this->createBook($bookOwner, '9789634567897');
        $review = $this->createReview($reviewAuthor, $book);

        $this
            ->actingAs($firstUser)
            ->post(route('reviews.like', $review));

        $this
            ->actingAs($secondUser)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $firstUser->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $secondUser->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 2);

        $this
            ->actingAs($firstUser)
            ->post(route('reviews.like', $review));

        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $firstUser->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $secondUser->id,
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseCount('review_likes', 1);
    }

    public function test_guest_is_redirected_to_login_when_liking_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewAuthor = User::factory()->create();

        $book = $this->createBook($bookOwner, '9789734567896');
        $review = $this->createReview($reviewAuthor, $book);

        $response = $this->post(route('reviews.like', $review));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseCount('review_likes', 0);
    }

    private function createBook(User $owner, string $isbn): Book
    {
        return Book::create([
            'user_id' => $owner->id,
            'title' => 'レビューいいねテスト書籍',
            'author' => 'テスト著者',
            'isbn' => $isbn,
            'published_date' => '2026-08-25',
            'description' => 'レビューいいね機能のテスト書籍です。',
            'image_url' => null,
        ]);
    }

    private function createReview(User $author, Book $book): Review
    {
        return Review::create([
            'user_id' => $author->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'レビューいいね機能のテストです。',
        ]);
    }
}
