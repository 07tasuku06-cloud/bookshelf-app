<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_detail_paginates_books_by_ten(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => 'ページネーションテスト',
        ]);

        for ($number = 1; $number <= 11; $number++) {
            $book = Book::create([
                'user_id' => $user->id,
                'title' => "ジャンル書籍{$number}",
                'author' => 'テスト著者',
                'isbn' => sprintf('979%010d', $number),
                'published_date' => '2026-08-25',
                'description' => 'ジャンル詳細のテストです。',
                'image_url' => null,
            ]);

            $book->genres()->attach($genre->id);
        }

        $response = $this
            ->actingAs($user)
            ->get(route('genres.show', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.show');

        $response->assertViewHas('genre', function (Genre $viewGenre) use ($genre) {
            return $viewGenre->is($genre);
        });

        $response->assertViewHas('books', function ($books) {
            return $books->count() === 10
                && $books->total() === 11;
        });
    }

    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除制限ジャンル',
        ]);

        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'ジャンル削除制限テスト',
            'author' => 'テスト著者',
            'isbn' => '9782234567890',
            'published_date' => '2026-08-25',
            'description' => 'ジャンルと紐付いた書籍です。',
            'image_url' => null,
        ]);

        $book->genres()->attach($genre->id);

        $response = $this
            ->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));

        $response->assertSessionHas(
            'error',
            '書籍が紐付いているジャンルは削除できません。'
        );

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
        ]);

        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    public function test_genre_index_displays_book_counts_in_name_order(): void
    {
        $user = User::factory()->create();

        $secondGenre = Genre::create([
            'name' => 'Bジャンル',
        ]);

        $firstGenre = Genre::create([
            'name' => 'Aジャンル',
        ]);

        $firstBook = Book::create([
            'user_id' => $user->id,
            'title' => 'ジャンル一覧テスト1',
            'author' => 'テスト著者',
            'isbn' => '9782234567891',
            'published_date' => '2026-08-25',
            'description' => 'ジャンル一覧のテストです。',
            'image_url' => null,
        ]);

        $secondBook = Book::create([
            'user_id' => $user->id,
            'title' => 'ジャンル一覧テスト2',
            'author' => 'テスト著者',
            'isbn' => '9782234567892',
            'published_date' => '2026-08-25',
            'description' => 'ジャンル一覧のテストです。',
            'image_url' => null,
        ]);

        $firstBook->genres()->attach([
            $firstGenre->id,
            $secondGenre->id,
        ]);

        $secondBook->genres()->attach($secondGenre->id);

        $response = $this
            ->actingAs($user)
            ->get(route('genres.index'));

        $response->assertOk();
        $response->assertViewIs('genres.index');

        $response->assertViewHas('genres', function ($genres) {
            return $genres->pluck('name')->all() === [
                'Aジャンル',
                'Bジャンル',
            ]
                && $genres[0]->books_count === 1
                && $genres[1]->books_count === 2;
        });

        $response->assertSee('Aジャンル');
        $response->assertSee('Bジャンル');
        $response->assertSee('1冊');
        $response->assertSee('2冊');
    }

    public function test_authenticated_user_can_create_genre(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('genres.store'), [
                'name' => '新規ジャンル',
            ]);

        $response->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', [
            'name' => '新規ジャンル',
        ]);
    }

    public function test_genre_creation_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), [
                'name' => '',
            ]);

        $response->assertRedirect(route('genres.create'));

        $response->assertSessionHasErrors([
            'name' => 'ジャンル名を入力してください。',
        ]);

        $this->assertDatabaseCount('genres', 0);
    }

    public function test_genre_creation_rejects_duplicate_name(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '登録済みジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('genres.create'))
            ->post(route('genres.store'), [
                'name' => '登録済みジャンル',
            ]);

        $response->assertRedirect(route('genres.create'));

        $response->assertSessionHasErrors([
            'name' => 'このジャンル名はすでに登録されています。',
        ]);

        $this->assertDatabaseCount('genres', 1);
    }

    public function test_authenticated_user_can_view_genre_edit_page(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '編集対象ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('genres.edit', $genre));

        $response->assertOk();
        $response->assertViewIs('genres.edit');

        $response->assertViewHas(
            'genre',
            function (Genre $viewGenre) use ($genre) {
                return $viewGenre->is($genre);
            }
        );

        $response->assertSee('編集対象ジャンル');
    }

    public function test_genre_can_be_updated_and_keep_its_own_name(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '更新前ジャンル',
        ]);

        $updateResponse = $this
            ->actingAs($user)
            ->put(route('genres.update', $genre), [
                'name' => '更新後ジャンル',
            ]);

        $updateResponse->assertRedirect(route('genres.index'));

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新後ジャンル',
        ]);

        $sameNameResponse = $this
            ->actingAs($user)
            ->from(route('genres.edit', $genre))
            ->put(route('genres.update', $genre), [
                'name' => '更新後ジャンル',
            ]);

        $sameNameResponse->assertRedirect(route('genres.index'));
        $sameNameResponse->assertSessionDoesntHaveErrors('name');

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '更新後ジャンル',
        ]);
    }

    public function test_genre_update_rejects_name_used_by_another_genre(): void
    {
        $user = User::factory()->create();

        Genre::create([
            'name' => '使用済みジャンル名',
        ]);

        $targetGenre = Genre::create([
            'name' => '更新対象ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('genres.edit', $targetGenre))
            ->put(route('genres.update', $targetGenre), [
                'name' => '使用済みジャンル名',
            ]);

        $response->assertRedirect(route('genres.edit', $targetGenre));

        $response->assertSessionHasErrors([
            'name' => 'このジャンル名はすでに登録されています。',
        ]);

        $this->assertDatabaseHas('genres', [
            'id' => $targetGenre->id,
            'name' => '更新対象ジャンル',
        ]);

        $this->assertDatabaseCount('genres', 2);
    }

    public function test_genre_without_books_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $genre = Genre::create([
            'name' => '削除可能ジャンル',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionMissing('error');

        $this->assertDatabaseMissing('genres', [
            'id' => $genre->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_when_attempting_genre_writes(): void
    {
        $genre = Genre::create([
            'name' => '既存ジャンル',
        ]);

        $storeResponse = $this->post(route('genres.store'), [
            'name' => 'ゲストが登録するジャンル',
        ]);

        $storeResponse->assertRedirect(route('login'));

        $updateResponse = $this->put(
            route('genres.update', $genre),
            [
                'name' => 'ゲストによる更新',
            ]
        );

        $updateResponse->assertRedirect(route('login'));

        $deleteResponse = $this->delete(
            route('genres.destroy', $genre)
        );

        $deleteResponse->assertRedirect(route('login'));

        $this->assertDatabaseMissing('genres', [
            'name' => 'ゲストが登録するジャンル',
        ]);

        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '既存ジャンル',
        ]);
    }
}
