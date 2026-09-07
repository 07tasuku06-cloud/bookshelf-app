<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_notification_routes(): void
    {
        $notificationId = (string) Str::uuid();

        $this->get(route('notifications.index'))
            ->assertRedirect(route('login'));

        $this->post(route('notifications.read', $notificationId))
            ->assertRedirect(route('login'));
    }

    public function test_index_displays_only_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $oldNotificationId = $this->createDatabaseNotification(
            $user,
            '古い通知',
            now()->subDay()
        );

        $newNotificationId = $this->createDatabaseNotification(
            $user,
            '新しい通知',
            now()
        );

        $this->createDatabaseNotification(
            $otherUser,
            '他人の通知',
            now()
        );

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas(
            'notifications',
            function ($notifications) use (
                $newNotificationId,
                $oldNotificationId
            ): bool {
                return $notifications->pluck('id')->all() === [
                    $newNotificationId,
                    $oldNotificationId,
                ];
            }
        );

        $response->assertSeeInOrder([
            '新しい通知',
            '古い通知',
        ]);

        $response->assertDontSee('他人の通知');
    }

    public function test_authenticated_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notificationId = $this->createDatabaseNotification(
            $user,
            '既読テスト通知',
            now()
        );

        $response = $this
            ->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.read', $notificationId));

        $response->assertRedirect(route('notifications.index'));
        $response->assertSessionHas(
            'success',
            '通知を既読にしました。'
        );

        $notification = $user->notifications()
            ->findOrFail($notificationId);

        $this->assertNotNull($notification->read_at);
    }

    public function test_authenticated_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notificationId = $this->createDatabaseNotification(
            $otherUser,
            '他人の未読通知',
            now()
        );

        $this->actingAs($user)
            ->post(route('notifications.read', $notificationId))
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }

    public function test_missing_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(
                route(
                    'notifications.read',
                    (string) Str::uuid()
                )
            )
            ->assertNotFound();
    }

    /**
     * 通知一覧・既読処理を検証するための通知データを作成する。
     */
    private function createDatabaseNotification(
        User $user,
        string $title,
        Carbon $createdAt
    ): string {
        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'Tests\\Fixtures\\ReadingPlanReminder',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'body' => '読書計画のお知らせです。',
                'timing' => 'three_days_before',
            ], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $notificationId;
    }
}
