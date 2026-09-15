<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanReminderTiming;
use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessReadingPlanRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-08 09:00:00')
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_updates_past_planned_reading_plans_to_overdue(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $pastPlan = $this->createReadingPlan(
            $user,
            $book,
            '2026-09-07'
        );

        $futurePlan = $this->createReadingPlan(
            $user,
            $book,
            '2026-09-09'
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('期限超過へ更新：1件')
            ->expectsOutput('リマインダー通知：0件')
            ->assertSuccessful();

        $this->assertSame(
            ReadingPlanStatus::Overdue,
            $pastPlan->refresh()->status
        );
        $this->assertSame(
            ReadingPlanStatus::Planned,
            $futurePlan->refresh()->status
        );
    }

    public function test_command_sends_notifications_for_three_supported_timings(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-11'
        );

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-08'
        );

        $threeDaysAfterPlan = $this->createReadingPlan(
            $user,
            $book,
            '2026-09-05'
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('期限超過へ更新：1件')
            ->expectsOutput('リマインダー通知：3件')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 3);

        $timings = $user->notifications()
            ->get()
            ->pluck('data')
            ->pluck('timing')
            ->all();

        $this->assertEqualsCanonicalizing([
            ReadingPlanReminderTiming::ThreeDaysBefore->value,
            ReadingPlanReminderTiming::OnDueDate->value,
            ReadingPlanReminderTiming::ThreeDaysAfter->value,
        ], $timings);

        $this->assertSame(
            ReadingPlanStatus::Overdue,
            $threeDaysAfterPlan->refresh()->status
        );
    }

    public function test_completed_and_unrelated_plans_are_not_notified(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $completedPlan = $this->createReadingPlan(
            $user,
            $book,
            '2026-09-08',
            ReadingPlanStatus::Completed
        );

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-09'
        );

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-04',
            ReadingPlanStatus::Overdue
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('期限超過へ更新：0件')
            ->expectsOutput('リマインダー通知：0件')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(
            ReadingPlanStatus::Completed,
            $completedPlan->refresh()->status
        );
    }

    public function test_running_command_twice_does_not_duplicate_notifications(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-11'
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('リマインダー通知：1件')
            ->assertSuccessful();

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('リマインダー通知：0件')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_same_plan_can_receive_different_timing_notifications(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook($user);

        $this->createReadingPlan(
            $user,
            $book,
            '2026-09-11'
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('リマインダー通知：1件')
            ->assertSuccessful();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-09-11 09:00:00')
        );

        $this->artisan('reading-plans:process-reminders')
            ->expectsOutput('リマインダー通知：1件')
            ->assertSuccessful();

        $this->assertDatabaseCount('notifications', 2);

        $timings = $user->notifications()
            ->get()
            ->pluck('data')
            ->pluck('timing')
            ->all();

        $this->assertEqualsCanonicalizing([
            ReadingPlanReminderTiming::ThreeDaysBefore->value,
            ReadingPlanReminderTiming::OnDueDate->value,
        ], $timings);
    }

    /**
     * テスト用の書籍を作成する。
     */
    private function createBook(User $user): Book
    {
        return Book::create([
            'user_id' => $user->id,
            'title' => 'Commandテスト書籍',
            'author' => 'Commandテスト著者',
        ]);
    }

    /**
     * 指定した期日と状態でテスト用読書計画を作成する。
     */
    private function createReadingPlan(
        User $user,
        Book $book,
        string $targetDate,
        ReadingPlanStatus $status = ReadingPlanStatus::Planned
    ): ReadingPlan {
        return ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed
                ? '2026-09-08 08:00:00'
                : null,
        ]);
    }
}
