<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_reading_plan_routes(): void
    {
        $response = $this->get(route('reading-plans.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_displays_only_authenticated_users_plans_filtered_by_status(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $plannedBook = $this->createBook($owner, '表示対象の書籍');
        $completedBook = $this->createBook($owner, '読了済みの書籍');
        $otherUsersBook = $this->createBook($otherUser, '他人の書籍');

        $plannedPlan = $this->createReadingPlan(
            $owner,
            $plannedBook
        );

        $this->createReadingPlan(
            $owner,
            $completedBook,
            [
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now(),
            ]
        );

        $this->createReadingPlan(
            $otherUser,
            $otherUsersBook
        );

        $response = $this
            ->actingAs($owner)
            ->get(route('reading-plans.index', [
                'status' => ReadingPlanStatus::Planned->value,
            ]));

        $response->assertOk();
        $response->assertViewIs('reading-plans.index');
        $response->assertViewHas(
            'currentStatus',
            ReadingPlanStatus::Planned->value
        );
        $response->assertViewHas(
            'readingPlans',
            function (LengthAwarePaginator $plans) use ($plannedPlan): bool {
                return $plans->total() === 1
                    && $plans->first()->is($plannedPlan)
                    && $plans->first()->relationLoaded('book');
            }
        );

        $response->assertSee('表示対象の書籍');
        $response->assertDontSee('読了済みの書籍');
        $response->assertDontSee('他人の書籍');
    }

    public function test_authenticated_user_can_store_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);
        $targetDate = today()->addDays(7)->toDateString();

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.store'), [
                'book_id' => $book->id,
                'target_date' => $targetDate,
            ]);

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => ReadingPlanStatus::Planned->value,
            'completed_at' => null,
        ]);
    }

    public function test_store_rejects_missing_book_and_past_target_date(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('reading-plans.create'))
            ->post(route('reading-plans.store'), [
                'book_id' => 999999,
                'target_date' => today()->subDay()->toDateString(),
            ]);

        $response->assertRedirect(route('reading-plans.create'));
        $response->assertSessionHasErrors([
            'book_id' => '選択された書籍は存在しません。',
            'target_date' => '期日は今日以降の日付を入力してください。',
        ]);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    public function test_owner_can_view_create_and_edit_pages(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);
        $readingPlan = $this->createReadingPlan($user, $book);

        $createResponse = $this
            ->actingAs($user)
            ->get(route('reading-plans.create'));

        $createResponse->assertOk();
        $createResponse->assertViewIs('reading-plans.create');
        $createResponse->assertSee($book->title);

        $editResponse = $this
            ->actingAs($user)
            ->get(route('reading-plans.edit', $readingPlan));

        $editResponse->assertOk();
        $editResponse->assertViewIs('reading-plans.edit');
    }

    public function test_owner_can_update_target_date(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);
        $readingPlan = $this->createReadingPlan($user, $book);
        $newTargetDate = today()->addDays(14)->toDateString();

        $response = $this
            ->actingAs($user)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => $newTargetDate,
            ]);

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseHas('reading_plans', [
            'id' => $readingPlan->id,
            'target_date' => $newTargetDate,
        ]);
    }

    public function test_owner_can_complete_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);
        $readingPlan = $this->createReadingPlan($user, $book);

        $response = $this
            ->actingAs($user)
            ->post(route('reading-plans.complete', $readingPlan));

        $response->assertRedirect(route('reading-plans.index'));

        $readingPlan->refresh();

        $this->assertSame(
            ReadingPlanStatus::Completed,
            $readingPlan->status
        );
        $this->assertNotNull($readingPlan->completed_at);
    }

    public function test_owner_can_delete_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);
        $readingPlan = $this->createReadingPlan($user, $book);

        $response = $this
            ->actingAs($user)
            ->delete(route('reading-plans.destroy', $readingPlan));

        $response->assertRedirect(route('reading-plans.index'));

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $readingPlan->id,
        ]);
    }

    public function test_non_owner_cannot_modify_reading_plan(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = $this->createBook($owner);
        $readingPlan = $this->createReadingPlan($owner, $book);
        $newTargetDate = today()->addDays(14)->toDateString();

        $this->actingAs($otherUser)
            ->get(route('reading-plans.edit', $readingPlan))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => $newTargetDate,
            ])
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->post(route('reading-plans.complete', $readingPlan))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->delete(route('reading-plans.destroy', $readingPlan))
            ->assertForbidden();
    }

    public function test_completed_plan_cannot_be_edited_updated_or_completed_again(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $readingPlan = $this->createReadingPlan(
            $user,
            $book,
            [
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now(),
            ]
        );

        $this->actingAs($user)
            ->get(route('reading-plans.edit', $readingPlan))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('reading-plans.update', $readingPlan), [
                'target_date' => today()->addDays(14)->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('reading-plans.complete', $readingPlan))
            ->assertForbidden();
    }

    private function createBook(
        User $owner,
        string $title = 'テスト書籍'
    ): Book {
        return Book::create([
            'user_id' => $owner->id,
            'title' => $title,
            'author' => 'テスト著者',
            'isbn' => null,
            'published_date' => null,
            'description' => null,
            'image_url' => null,
        ]);
    }

    /**
     * テスト用の読書計画を作成する。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createReadingPlan(
        User $user,
        Book $book,
        array $attributes = []
    ): ReadingPlan {
        return ReadingPlan::create(array_merge([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => today()->addDay(),
            'status' => ReadingPlanStatus::Planned,
            'completed_at' => null,
        ], $attributes));
    }
}
