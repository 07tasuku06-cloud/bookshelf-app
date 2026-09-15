<?php

namespace App\Enums;

enum ReadingPlanReminderTiming: string
{
    case ThreeDaysBefore = 'three_days_before';
    case OnDueDate = 'on_due_date';
    case ThreeDaysAfter = 'three_days_after';

    /**
     * 通知タイミングに対応するタイトルを返す。
     */
    public function title(): string
    {
        return match ($this) {
            self::ThreeDaysBefore => '読書期限の3日前です',
            self::OnDueDate => '本日が読書期限です',
            self::ThreeDaysAfter => '読書期限を過ぎています',
        };
    }

    /**
     * 書籍名と期日を含む通知本文を返す。
     */
    public function body(
        string $bookTitle,
        string $targetDate
    ): string {
        return match ($this) {
            self::ThreeDaysBefore => "「{$bookTitle}」の読書期限は{$targetDate}です。",
            self::OnDueDate => "「{$bookTitle}」の読書期限は本日です。",
            self::ThreeDaysAfter => "「{$bookTitle}」の読書期限を3日過ぎています。",
        };
    }
}
