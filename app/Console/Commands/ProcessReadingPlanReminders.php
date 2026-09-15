<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanReminderTiming;
use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProcessReadingPlanReminders extends Command
{
    /**
     * 実行時に使用するコマンド名。
     *
     * @var string
     */
    protected $signature = 'reading-plans:process-reminders';

    /**
     * コマンドの説明。
     *
     * @var string
     */
    protected $description = '読書計画の期限状態を更新し、期日別リマインダーを送信する';

    /**
     * 期限超過状態の更新とリマインダー送信を実行する。
     *
     * @return int Artisanコマンドの終了コード
     */
    public function handle(): int
    {
        $today = CarbonImmutable::today();

        $overdueCount = ReadingPlan::query()
            ->where(
                'status',
                ReadingPlanStatus::Planned->value
            )
            ->where(
                'target_date',
                '<',
                $today->toDateString()
            )
            ->update([
                'status' => ReadingPlanStatus::Overdue->value,
                'updated_at' => now(),
            ]);

        $targetDates = [
            $today->addDays(3)->toDateString(),
            $today->toDateString(),
            $today->subDays(3)->toDateString(),
        ];

        $readingPlanIds = ReadingPlan::query()
            ->whereIn('status', [
                ReadingPlanStatus::Planned->value,
                ReadingPlanStatus::Overdue->value,
            ])
            ->whereIn('target_date', $targetDates)
            ->pluck('id')
            ->map(
                static fn (int|string $id): int => (int) $id
            );

        $sentCount = $readingPlanIds->sum(
            fn (int $readingPlanId): int => $this->processReminder(
                $readingPlanId,
                $today
            )
        );

        $this->info(
            "期限超過へ更新：{$overdueCount}件"
        );
        $this->info(
            "リマインダー通知：{$sentCount}件"
        );

        return self::SUCCESS;
    }

    /**
     * 1件の読書計画を排他制御し、未送信の場合だけ通知する。
     *
     * @param  int  $readingPlanId  処理対象の読書計画ID
     * @param  CarbonImmutable  $today  判定基準日
     * @return int 送信した通知数。未送信の場合は0
     */
    private function processReminder(
        int $readingPlanId,
        CarbonImmutable $today
    ): int {
        return DB::transaction(
            function () use (
                $readingPlanId,
                $today
            ): int {
                $readingPlan = ReadingPlan::query()
                    ->with(['book', 'user'])
                    ->lockForUpdate()
                    ->find($readingPlanId);

                if ($readingPlan === null) {
                    return 0;
                }

                $timing = $this->determineTiming(
                    $readingPlan,
                    $today
                );

                if (
                    $timing === null
                    || $this->reminderAlreadyExists(
                        $readingPlan,
                        $timing
                    )
                ) {
                    return 0;
                }

                Notification::send(
                    $readingPlan->user,
                    new ReadingPlanReminder(
                        $readingPlan,
                        $timing
                    )
                );

                return 1;
            }
        );
    }

    /**
     * 読書計画の状態と期日から通知タイミングを決定する。
     *
     * @param  ReadingPlan  $readingPlan  判定対象の読書計画
     * @param  CarbonImmutable  $today  判定基準日
     * @return ReadingPlanReminderTiming|null 通知タイミング。対象外の場合はnull
     */
    private function determineTiming(
        ReadingPlan $readingPlan,
        CarbonImmutable $today
    ): ?ReadingPlanReminderTiming {
        $targetDate = $readingPlan
            ->target_date
            ->toDateString();

        return match (true) {
            $readingPlan->status === ReadingPlanStatus::Planned
                && $targetDate === $today
                    ->addDays(3)
                    ->toDateString() => ReadingPlanReminderTiming::ThreeDaysBefore,

            $readingPlan->status === ReadingPlanStatus::Planned
                && $targetDate === $today->toDateString() => ReadingPlanReminderTiming::OnDueDate,

            $readingPlan->status === ReadingPlanStatus::Overdue
                && $targetDate === $today
                    ->subDays(3)
                    ->toDateString() => ReadingPlanReminderTiming::ThreeDaysAfter,

            default => null,
        };
    }

    /**
     * 同じ読書計画・通知タイミングの通知が送信済みか確認する。
     *
     * @param  ReadingPlan  $readingPlan  確認対象の読書計画
     * @param  ReadingPlanReminderTiming  $timing  確認対象の通知タイミング
     * @return bool 送信済みの場合はtrue
     */
    private function reminderAlreadyExists(
        ReadingPlan $readingPlan,
        ReadingPlanReminderTiming $timing
    ): bool {
        return $readingPlan->user
            ->notifications()
            ->where(
                'type',
                ReadingPlanReminder::class
            )
            ->where(
                'data->reading_plan_id',
                $readingPlan->id
            )
            ->where(
                'data->timing',
                $timing->value
            )
            ->exists();
    }
}
