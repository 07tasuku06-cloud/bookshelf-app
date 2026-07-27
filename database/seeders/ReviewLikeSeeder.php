<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $reviews = Review::orderBy('id')->get();

        foreach ($reviews as $reviewIndex => $review) {
            // レビューごとに0、1、2、3人を繰り返す
            $likeCount = $reviewIndex % 4;

            $eligibleUsers = $users
                ->where('id', '!=', $review->user_id)
                ->values();

            $userIds = [];

            for ($i = 0; $i < $likeCount; $i++) {
                $userIndex = ($reviewIndex + $i) % $eligibleUsers->count();
                $userIds[] = $eligibleUsers[$userIndex]->id;
            }

            $review->likedUsers()->syncWithoutDetaching($userIds);
        }
    }
}
