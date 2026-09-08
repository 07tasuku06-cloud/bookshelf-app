<?php

namespace App\Notifications;

use App\Enums\ReadingPlanReminderTiming;
use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    /**
     * 通知対象の読書計画と通知タイミングを受け取る。
     */
    public function __construct(
        private readonly ReadingPlan $readingPlan,
        private readonly ReadingPlanReminderTiming $timing
    ) {}

    /**
     * 通知をデータベースへ保存する。
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * データベースへ保存する通知内容を返す。
     *
     * @return array<string, int|string|null>
     */
    public function toDatabase(object $notifiable): array
    {
        $this->readingPlan->loadMissing('book');

        return [
            'title' => $this->timing->title(),
            'body' => $this->timing->body(
                $this->readingPlan->book->title,
                $this->readingPlan->target_date->format('Y-m-d')
            ),
            'timing' => $this->timing->value,
            'reading_plan_id' => $this->readingPlan->id,
            'book_id' => $this->readingPlan->book_id,
            'target_date' => $this->readingPlan
                ->target_date
                ->format('Y-m-d'),
        ];
    }
}
