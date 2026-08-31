<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->assertTrue($book->user->is($user));
    }

    public function test_book_belongs_to_many_genres(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $firstGenre = Genre::create([
            'name' => 'PHP',
        ]);

        $secondGenre = Genre::create([
            'name' => 'Laravel',
        ]);

        $book->genres()->attach([
            $firstGenre->id,
            $secondGenre->id,
        ]);

        $book->load('genres');

        $this->assertCount(2, $book->genres);
        $this->assertTrue($book->genres->contains($firstGenre));
        $this->assertTrue($book->genres->contains($secondGenre));
    }

    public function test_book_has_many_reviews(): void
    {
        $bookOwner = User::factory()->create();
        $firstReviewer = User::factory()->create();
        $secondReviewer = User::factory()->create();

        $book = $this->createBook($bookOwner);

        $firstReview = Review::create([
            'user_id' => $firstReviewer->id,
            'book_id' => $book->id,
            'rating' => 4,
            'comment' => '1件目のレビューです。',
        ]);

        $secondReview = Review::create([
            'user_id' => $secondReviewer->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '2件目のレビューです。',
        ]);

        $book->load('reviews');

        $this->assertCount(2, $book->reviews);
        $this->assertTrue($book->reviews->contains($firstReview));
        $this->assertTrue($book->reviews->contains($secondReview));
    }

    public function test_book_belongs_to_many_favorited_users(): void
    {
        $bookOwner = User::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $book = $this->createBook($bookOwner);

        $firstUser->favoriteBooks()->attach($book->id);
        $secondUser->favoriteBooks()->attach($book->id);

        $book->load('favoritedUsers');

        $this->assertCount(2, $book->favoritedUsers);
        $this->assertTrue($book->favoritedUsers->contains($firstUser));
        $this->assertTrue($book->favoritedUsers->contains($secondUser));
    }

    public function test_published_date_is_cast_to_date(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->assertInstanceOf(Carbon::class, $book->published_date);
        $this->assertSame('2026-08-30', $book->published_date->toDateString());
    }

    private function createBook(User $user): Book
    {
        return Book::create([
            'user_id' => $user->id,
            'title' => 'Modelテスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-30',
            'description' => 'Bookモデルのテストです。',
            'image_url' => null,
        ]);
    }
}
