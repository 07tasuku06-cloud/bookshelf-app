<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * 評価1から5までのレビュー初期データを登録する。
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        $comments = [
            1 => '期待していた内容とは異なり、あまり満足できませんでした。',
            2 => '参考になる部分もありましたが、少し物足りなく感じました。',
            3 => '内容に学びがあり、参考になる部分が多い一冊でした。',
            4 => 'とても読みやすく、印象に残る内容でした。',
            5 => '非常に満足できました。多くの人に読んでほしい一冊です。',
        ];

        foreach ($books as $bookIndex => $book) {
            $reviewCount = random_int(2, 4);
            $reviewers = $users
                ->random($reviewCount)
                ->values();

            foreach ($reviewers as $reviewIndex => $user) {
                $rating = (($bookIndex + $reviewIndex) % 5) + 1;

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => '「'.$book->title.'」は'.$comments[$rating],
                ]);
            }
        }
    }
}
