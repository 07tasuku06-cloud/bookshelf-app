<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanReminderTiming;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class ReadingPlanReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_days_before_notification_is_saved_to_database(): void
    {
        $notification = $this->sendNotification(
            ReadingPlanReminderTiming::ThreeDaysBefore
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(
            ReadingPlanReminder::class,
            $notification->type
        );
        $this->assertSame(
            '読書期限の3日前です',
            $notification->data['title']
        );
        $this->assertSame(
            '「通知テスト書籍」の読書期限は2026-09-10です。',
            $notification->data['body']
        );
        $this->assertSame(
            'three_days_before',
            $notification->data['timing']
        );
        $this->assertSame(
            '2026-09-10',
            $notification->data['target_date']
        );
        $this->assertIsInt(
            $notification->data['reading_plan_id']
        );
        $this->assertIsInt(
            $notification->data['book_id']
        );
        $this->assertNull($notification->read_at);
    }

    public function test_on_due_date_notification_is_saved_to_database(): void
    {
        $notification = $this->sendNotification(
            ReadingPlanReminderTiming::OnDueDate
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(
            '本日が読書期限です',
            $notification->data['title']
        );
        $this->assertSame(
            '「通知テスト書籍」の読書期限は本日です。',
            $notification->data['body']
        );
        $this->assertSame(
            'on_due_date',
            $notification->data['timing']
        );
    }

    public function test_three_days_after_notification_is_saved_to_database(): void
    {
        $notification = $this->sendNotification(
            ReadingPlanReminderTiming::ThreeDaysAfter
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(
            '読書期限を過ぎています',
            $notification->data['title']
        );
        $this->assertSame(
            '「通知テスト書籍」の読書期限を3日過ぎています。',
            $notification->data['body']
        );
        $this->assertSame(
            'three_days_after',
            $notification->data['timing']
        );
    }

    /**
     * テスト用読書計画を作成し、データベース通知を送信する。
     */
    private function sendNotification(
        ReadingPlanReminderTiming $timing
    ): DatabaseNotification {
        $user = User::factory()->create();

        $book = Book::create([
            'user_id' => $user->id,
            'title' => '通知テスト書籍',
            'author' => '通知テスト著者',
        ]);

        $readingPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => '2026-09-10',
        ]);

        $user->notify(
            new ReadingPlanReminder(
                $readingPlan,
                $timing
            )
        );

        return $user->notifications()->sole();
    }
}
