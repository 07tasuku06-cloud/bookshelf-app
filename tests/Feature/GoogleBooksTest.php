<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_books.url' => 'https://www.googleapis.com/books/v1/volumes',
            'services.google_books.key' => null,
        ]);
    }

    public function test_guest_cannot_fetch_book_by_isbn(): void
    {
        Http::fake();

        $response = $this->get(route('books.fetch-by-isbn', [
            'isbn' => '9784101010014',
        ]));

        $response->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_invalid_isbn_returns_japanese_validation_error(): void
    {
        Http::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson(route('books.fetch-by-isbn', [
                'isbn' => 'abcdefghijklm',
            ]));

        $response->assertUnprocessable();
        $response->assertExactJson([
            'error' => 'ISBNは13桁の数字で入力してください。',
        ]);

        Http::assertNothingSent();
    }

    public function test_authenticated_user_can_fetch_book_information(): void
    {
        config([
            'services.google_books.key' => 'test-api-key',
        ]);

        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '吾輩は猫である',
                            'authors' => [
                                '夏目漱石',
                                '共同著者',
                            ],
                            'publishedDate' => '1905',
                            'description' => '猫の視点から描いた小説です。',
                            'imageLinks' => [
                                'thumbnail' => 'http://example.com/book.jpg',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson(route('books.fetch-by-isbn', [
                'isbn' => '9784101010014',
            ]));

        $response->assertOk();

        $response->assertExactJson([
            'title' => '吾輩は猫である',
            'author' => '夏目漱石、共同著者',
            'published_date' => '1905-01-01',
            'description' => '猫の視点から描いた小説です。',
            'image_url' => 'https://example.com/book.jpg',
        ]);

        Http::assertSent(
            function (Request $request): bool {
                return str_starts_with(
                    $request->url(),
                    'https://www.googleapis.com/books/v1/volumes'
                )
                    && $request['q'] === 'isbn:9784101010014'
                    && (int) $request['maxResults'] === 1
                    && $request['key'] === 'test-api-key';
            }
        );
    }

    public function test_year_and_month_published_date_is_normalized(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => '年月のみの書籍',
                            'authors' => ['テスト著者'],
                            'publishedDate' => '2026-09',
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson(route('books.fetch-by-isbn', [
                'isbn' => '9781234567890',
            ]));

        $response->assertOk();
        $response->assertJsonPath(
            'published_date',
            '2026-09-01'
        );
        $response->assertJsonPath('description', null);
        $response->assertJsonPath('image_url', null);
    }

    public function test_not_found_book_returns_json_404(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 0,
                'items' => [],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson(route('books.fetch-by-isbn', [
                'isbn' => '9789999999999',
            ]));

        $response->assertNotFound();
        $response->assertExactJson([
            'error' => '該当する書籍が見つかりませんでした。',
        ]);
    }

    public function test_google_books_api_error_returns_json_502(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson(route('books.fetch-by-isbn', [
                'isbn' => '9784101010014',
            ]));

        $response->assertStatus(502);
        $response->assertExactJson([
            'error' => '書籍情報の取得に失敗しました。',
        ]);
    }
}
