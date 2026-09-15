<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'user_email' => 'yamada@example.com',
                'book_title' => 'リーダブルコード',
                'target_date' => today()->addDays(7),
                'status' => ReadingPlanStatus::Planned,
                'completed_at' => null,
            ],
            [
                'user_email' => 'yamada@example.com',
                'book_title' => '7つの習慣',
                'target_date' => today()->subDays(5),
                'status' => ReadingPlanStatus::Overdue,
                'completed_at' => null,
            ],
            [
                'user_email' => 'yamada@example.com',
                'book_title' => '吾輩は猫である',
                'target_date' => today()->subDays(14),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now()->subDays(10),
            ],
            [
                'user_email' => 'suzuki@example.com',
                'book_title' => 'Clean Code',
                'target_date' => today()->addDays(14),
                'status' => ReadingPlanStatus::Planned,
                'completed_at' => null,
            ],
            [
                'user_email' => 'suzuki@example.com',
                'book_title' => 'FACTFULNESS',
                'target_date' => today()->subDays(30),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => now()->subDays(20),
            ],
            [
                'user_email' => 'tanaka@example.com',
                'book_title' => 'サピエンス全史',
                'target_date' => today()->subDays(2),
                'status' => ReadingPlanStatus::Overdue,
                'completed_at' => null,
            ],
        ];

        foreach ($plans as $planData) {
            $user = User::where('email', $planData['user_email'])->firstOrFail();
            $book = Book::where('title', $planData['book_title'])->firstOrFail();

            ReadingPlan::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                ],
                [
                    'target_date' => $planData['target_date'],
                    'status' => $planData['status'],
                    'completed_at' => $planData['completed_at'],
                ]
            );
        }
    }
}
