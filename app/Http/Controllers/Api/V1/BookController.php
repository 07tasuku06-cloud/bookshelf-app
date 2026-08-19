<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBookRequest;
use App\Http\Requests\Api\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate(
            [
                'keyword' => ['nullable', 'string', 'max:255'],
                'genre_id' => [
                    'nullable',
                    'integer',
                    'exists:genres,id',
                ],
                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ],
            [
                'keyword.string' => 'キーワードは文字列で入力してください。',
                'keyword.max' => 'キーワードは255文字以内で入力してください。',
                'genre_id.integer' => 'ジャンルIDは整数で入力してください。',
                'genre_id.exists' => '指定されたジャンルは存在しません。',
                'per_page.integer' => '表示件数は整数で入力してください。',
                'per_page.min' => '表示件数は1件以上で指定してください。',
                'per_page.max' => '表示件数は100件以内で指定してください。',
            ]
        );

        $keyword = $validated['keyword'] ?? null;
        $genreId = $validated['genre_id'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 10);

        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword, function ($query, $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%")
                        ->orWhere('isbn', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when($genreId, function ($query, $genreId) {
                $query->whereHas(
                    'genres',
                    function ($query) use ($genreId) {
                        $query->where('genres.id', $genreId);
                    }
                );
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        $book = DB::transaction(function () use ($validated) {
            $genreIds = $validated['genres'];
            unset($validated['genres']);

            $book = Book::create($validated);

            $book->genres()->sync($genreIds);

            return $book;
        });

        $book
            ->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Book $book)
    {
        $book->load([
            'genres',
            'reviews' => function ($query) {
                $query
                    ->with('user')
                    ->latest();
            },
        ]);

        $book
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    public function update(
        UpdateBookRequest $request,
        Book $book
    ) {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $book) {
            $genreIds = $validated['genres'];
            unset($validated['genres']);

            $book->update($validated);

            $book->genres()->sync($genreIds);
        });

        $book
            ->load('genres')
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    public function destroy(Book $book)
    {
        $book->delete();

        return response()->noContent();
    }
}
