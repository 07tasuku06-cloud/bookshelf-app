<?php

namespace Tests\Feature\Api;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * トークン発行時の必須項目を検証する。
     */
    public function test_token_store_requires_credentials_and_device_name(): void
    {
        $response = $this->postJson(
            route('api.v1.tokens.store'),
            []
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email',
            'password',
            'device_name',
        ]);
        $response->assertJsonPath(
            'errors.email.0',
            'メールアドレスを入力してください。'
        );
        $response->assertJsonPath(
            'errors.password.0',
            'パスワードを入力してください。'
        );
        $response->assertJsonPath(
            'errors.device_name.0',
            '端末名を入力してください。'
        );
    }

    /**
     * 誤ったパスワードではトークンを発行しない。
     */
    public function test_invalid_credentials_cannot_create_token(): void
    {
        User::factory()->create([
            'email' => 'token@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson(
            route('api.v1.tokens.store'),
            [
                'email' => 'token@example.com',
                'password' => 'wrong-password',
                'device_name' => 'postman',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $response->assertJsonPath(
            'errors.email.0',
            'メールアドレスまたはパスワードが正しくありません。'
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    /**
     * 正しい認証情報でAPIトークンを発行する。
     */
    public function test_valid_credentials_can_create_token(): void
    {
        $user = User::factory()->create([
            'email' => 'token@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson(
            route('api.v1.tokens.store'),
            [
                'email' => 'token@example.com',
                'password' => 'correct-password',
                'device_name' => 'postman',
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath(
            'token_type',
            'Bearer'
        );
        $response->assertJsonStructure([
            'token',
            'token_type',
        ]);

        $this->assertNotEmpty(
            $response->json('token')
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'postman',
            ]
        );
    }

    /**
     * APIで発行したBearerトークンを使って書籍を登録する。
     */
    public function test_issued_token_can_access_protected_book_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'bearer@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $genre = Genre::create([
            'name' => 'Bearer認証テスト',
        ]);

        $tokenResponse = $this->postJson(
            route('api.v1.tokens.store'),
            [
                'email' => 'bearer@example.com',
                'password' => 'correct-password',
                'device_name' => 'integration-test',
            ]
        );

        $tokenResponse->assertCreated();

        $bookResponse = $this
            ->withToken(
                (string) $tokenResponse->json('token')
            )
            ->postJson('/api/v1/books', [
                'title' => 'Bearer認証で登録する書籍',
                'author' => '認証テスト著者',
                'isbn' => '9789999999911',
                'published_date' => '2026-09-08',
                'description' => '発行したトークンを利用する統合テストです。',
                'image_url' => null,
                'genres' => [$genre->id],
            ]);

        $bookResponse->assertCreated();
        $bookResponse->assertJsonPath(
            'data.user_id',
            $user->id
        );

        $bookId = $bookResponse->json('data.id');

        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'user_id' => $user->id,
            'title' => 'Bearer認証で登録する書籍',
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $bookId,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 未認証ユーザーはトークンを失効できない。
     */
    public function test_guest_cannot_delete_current_token(): void
    {
        $response = $this->deleteJson(
            route('api.v1.tokens.destroy')
        );

        $response->assertUnauthorized();
    }

    /**
     * 使用中のトークンだけを失効させる。
     */
    public function test_authenticated_user_can_delete_current_token(): void
    {
        $user = User::factory()->create();

        $currentToken = $user->createToken('current-device');
        $otherToken = $user->createToken('other-device');

        $response = $this
            ->withToken($currentToken->plainTextToken)
            ->deleteJson(route('api.v1.tokens.destroy'));

        $response->assertNoContent();

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'id' => $currentToken->accessToken->id,
            ]
        );

        $this->assertDatabaseHas(
            'personal_access_tokens',
            [
                'id' => $otherToken->accessToken->id,
            ]
        );
    }
}
