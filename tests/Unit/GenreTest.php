<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_belongs_to_many_books(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'ジャンルテスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567891',
            'published_date' => '2026-08-30',
            'description' => 'Genreモデルのテストです。',
            'image_url' => null,
        ]);

        $genre = Genre::create([
            'name' => 'Laravel',
        ]);

        $book->genres()->attach($genre->id);
        $genre->load('books');

        $this->assertCount(1, $genre->books);
        $this->assertTrue($genre->books->contains($book));
    }
}
