<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = Book::create([
            'user_id' => $bookOwner->id,
            'title' => 'レビュー投稿テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9783234567890',
            'published_date' => '2026-08-25',
            'description' => 'レビュー投稿の対象です。',
            'image_url' => null,
        ]);

        $response = $this
            ->actingAs($reviewer)
            ->post(route('reviews.store', $book), [
                'rating' => 5,
                'comment' => 'とても参考になりました。',
            ]);

        $response->assertRedirect(route('books.show', $book));
        $response->assertSessionHas(
            'success',
            'レビューを投稿しました。'
        );

        $this->assertDatabaseHas('reviews', [
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => 'とても参考になりました。',
        ]);

        $review = Review::where([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
        ])->firstOrFail();

        $this->assertSame(5, $review->rating);
    }

    public function test_review_creation_returns_japanese_validation_messages(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = Book::create([
            'user_id' => $bookOwner->id,
            'title' => 'レビューバリデーション書籍',
            'author' => 'テスト著者',
            'isbn' => '9783234567891',
            'published_date' => '2026-08-25',
            'description' => 'バリデーションテストの対象です。',
            'image_url' => null,
        ]);

        $response = $this
            ->actingAs($reviewer)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 0,
                'comment' => '',
            ]);

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHasErrors([
            'rating' => '評価は1から5の間で選択してください。',
            'comment' => 'コメントを入力してください。',
        ]);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_author_can_view_edit_page(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = Book::create([
            'user_id' => $bookOwner->id,
            'title' => 'レビュー編集画面テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9783234567892',
            'published_date' => '2026-08-25',
            'description' => 'レビュー編集画面の対象です。',
            'image_url' => null,
        ]);

        $review = Review::create([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '編集前のレビューコメントです。',
        ]);

        $response = $this
            ->actingAs($reviewer)
            ->get(route('reviews.edit', $review));

        $response->assertOk();
        $response->assertViewIs('reviews.edit');

        $response->assertViewHas(
            'review',
            function (Review $viewReview) use ($review) {
                return $viewReview->is($review)
                    && $viewReview->rating === 4
                    && $viewReview->comment
                    === '編集前のレビューコメントです。';
            }
        );

        $response->assertSee('編集前のレビューコメントです。');
    }

    public function test_review_author_can_update_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567893');
        $review = $this->createReview($reviewer, $book);

        $response = $this
            ->actingAs($reviewer)
            ->put(route('reviews.update', $review), [
                'rating' => 2,
                'comment' => '更新後のレビューコメントです。',
            ]);

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'レビューを更新しました。'
        );

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 2,
            'comment' => '更新後のレビューコメントです。',
        ]);
    }

    public function test_review_update_returns_japanese_validation_messages(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567894');

        $review = $this->createReview($reviewer, $book, [
            'rating' => 4,
            'comment' => '更新前のレビューです。',
        ]);

        $response = $this
            ->actingAs($reviewer)
            ->from(route('reviews.edit', $review))
            ->put(route('reviews.update', $review), [
                'rating' => 6,
                'comment' => '',
            ]);

        $response->assertRedirect(route('reviews.edit', $review));

        $response->assertSessionHasErrors([
            'rating' => '評価は1から5の間で選択してください。',
            'comment' => 'コメントを入力してください。',
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '更新前のレビューです。',
        ]);
    }

    public function test_non_author_cannot_edit_update_or_delete_review(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567895');
        $review = $this->createReview($reviewer, $book);

        $editResponse = $this
            ->actingAs($otherUser)
            ->get(route('reviews.edit', $review));

        $editResponse->assertForbidden();

        $updateResponse = $this
            ->actingAs($otherUser)
            ->put(route('reviews.update', $review), [
                'rating' => 1,
                'comment' => '不正な更新です。',
            ]);

        $updateResponse->assertForbidden();

        $deleteResponse = $this
            ->actingAs($otherUser)
            ->delete(route('reviews.destroy', $review));

        $deleteResponse->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'rating' => 4,
        ]);
    }

    public function test_review_author_can_delete_review_and_related_likes(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();
        $liker = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567896');
        $review = $this->createReview($reviewer, $book);

        $liker->likedReviews()->attach($review->id);

        $response = $this
            ->actingAs($reviewer)
            ->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHas(
            'success',
            'レビューを削除しました。'
        );

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);

        $this->assertDatabaseMissing('review_likes', [
            'review_id' => $review->id,
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_when_attempting_review_writes(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567897');
        $review = $this->createReview($reviewer, $book);

        $storeResponse = $this->post(
            route('reviews.store', $book),
            [
                'rating' => 5,
                'comment' => 'ゲストによる投稿です。',
            ]
        );

        $storeResponse->assertRedirect(route('login'));

        $updateResponse = $this->put(
            route('reviews.update', $review),
            [
                'rating' => 1,
                'comment' => 'ゲストによる更新です。',
            ]
        );

        $updateResponse->assertRedirect(route('login'));

        $deleteResponse = $this->delete(
            route('reviews.destroy', $review)
        );

        $deleteResponse->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 1);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $reviewer->id,
            'rating' => 4,
            'comment' => 'テストレビューです。',
        ]);
    }

    private function createBook(User $owner, string $isbn): Book
    {
        return Book::create([
            'user_id' => $owner->id,
            'title' => '共通テスト書籍',
            'author' => '共通テスト著者',
            'isbn' => $isbn,
            'published_date' => '2026-08-25',
            'description' => 'レビュー用の共通書籍です。',
            'image_url' => null,
        ]);
    }

    private function createReview(
        User $reviewer,
        Book $book,
        array $attributes = []
    ): Review {
        return Review::create(array_merge([
            'user_id' => $reviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => 'テストレビューです。',
        ], $attributes));
    }

    public function test_user_cannot_create_duplicate_review_for_same_book(): void
    {
        $bookOwner = User::factory()->create();
        $reviewer = User::factory()->create();

        $book = $this->createBook($bookOwner, '9783234567898');

        $this->createReview($reviewer, $book);

        $response = $this
            ->actingAs($reviewer)
            ->from(route('books.show', $book))
            ->post(route('reviews.store', $book), [
                'rating' => 5,
                'comment' => '2件目のレビューです。',
            ]);

        $response->assertRedirect(route('books.show', $book));

        $response->assertSessionHasErrors([
            'comment' => 'この書籍にはすでにレビューを投稿しています。',
        ]);

        $this->assertDatabaseCount('reviews', 1);
    }
}
