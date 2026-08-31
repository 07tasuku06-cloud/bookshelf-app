<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_book_index_from_both_urls(): void
    {
        $homeResponse = $this->get(route('home'));

        $homeResponse->assertOk();
        $homeResponse->assertViewIs('books.index');

        $booksResponse = $this->get(route('books.index'));

        $booksResponse->assertOk();
        $booksResponse->assertViewIs('books.index');
    }

    public function test_guest_can_view_book_detail(): void
    {
        $user = User::factory()->create();

        $book = Book::create([
            'user_id' => $user->id,
            'title' => '詳細表示テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9781234567890',
            'published_date' => '2026-08-25',
            'description' => '書籍詳細画面のテストです。',
            'image_url' => null,
        ]);

        $response = $this->get(route('books.show', $book));

        $response->assertOk();
        $response->assertViewIs('books.show');
        $response->assertViewHas('book', function (Book $viewBook) use ($book) {
            return $viewBook->is($book);
        });
    }

    public function test_guest_is_redirected_to_login_from_authentication_required_pages(): void
    {
        $protectedUrls = [
            route('books.create'),
            route('genres.index'),
            route('genres.create'),
            route('favorites.index'),
        ];

        foreach ($protectedUrls as $url) {
            $response = $this->get($url);

            $response->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_user_can_view_authentication_required_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $protectedPages = [
            route('books.create') => 'books.create',
            route('genres.index') => 'genres.index',
            route('genres.create') => 'genres.create',
            route('favorites.index') => 'favorites.index',
        ];

        foreach ($protectedPages as $url => $view) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertViewIs($view);
        }
    }
}
