<?php

namespace Tests\Feature\Api;

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
