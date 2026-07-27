<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        // 最初の10冊に3件、最後の1冊に2件、合計32件
        $reviewCounts = [
            3,
            3,
            3,
            3,
            3,
            3,
            3,
            3,
            3,
            3,
            2,
        ];

        $comments = [
            3 => '内容に学びがあり、参考になる部分が多い一冊でした。',
            4 => 'とても読みやすく、印象に残る内容でした。',
            5 => '非常に満足できました。ぜひ多くの人に読んでほしい一冊です。',
        ];

        foreach ($books as $bookIndex => $book) {
            $reviewCount = $reviewCounts[$bookIndex];

            for ($i = 0; $i < $reviewCount; $i++) {
                $user = $users[($bookIndex + $i) % $users->count()];
                $rating = 3 + (($bookIndex + $i) % 3);

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => '「' . $book->title . '」は' . $comments[$rating],
                ]);
            }
        }
    }
}
