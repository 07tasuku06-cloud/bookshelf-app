<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $books = Book::orderBy('id')->get();

        foreach ($users as $userIndex => $user) {
            // ユーザーごとに3、4、5、3、4冊
            $favoriteCount = 3 + ($userIndex % 3);
            $bookIds = [];

            for ($i = 0; $i < $favoriteCount; $i++) {
                $bookIndex = ($userIndex * 2 + $i) % $books->count();
                $bookIds[] = $books[$bookIndex]->id;
            }

            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
