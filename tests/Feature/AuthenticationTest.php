<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_and_registration_pages(): void
    {
        $loginResponse = $this->get(route('login'));

        $loginResponse->assertOk();
        $loginResponse->assertViewIs('auth.login');

        $registerResponse = $this->get(route('register'));

        $registerResponse->assertOk();
        $registerResponse->assertViewIs('auth.register');
    }

    public function test_authenticated_user_is_redirected_from_authentication_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $loginResponse = $this->get(route('login'));

        $loginResponse->assertRedirect(route('home'));

        $registerResponse = $this->get(route('register'));

        $registerResponse->assertRedirect(route('home'));
    }

    public function test_user_can_register_with_valid_information(): void
    {
        $response = $this->post(route('register'), [
            'name' => '登録テストユーザー',
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'name' => '登録テストユーザー',
            'email' => 'register@example.com',
        ]);
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $response = $this
            ->from(route('register'))
            ->post(route('register'), [
                'name' => '',
                'email' => '',
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'name',
            'email',
            'password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $response = $this
            ->from(route('register'))
            ->post(route('register'), [
                'name' => '重複登録ユーザー',
                'email' => 'duplicate@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
        ]);

        $response = $this
            ->from(route('login'))
            ->post(route('login'), [
                'email' => 'login@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this
            ->from(route('login'))
            ->post(route('login'), [
                'email' => '',
                'password' => '',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email',
            'password',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('logout'));

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
