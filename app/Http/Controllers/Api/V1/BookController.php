<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexBookRequest;
use App\Http\Requests\Api\StoreBookRequest;
use App\Http\Requests\Api\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 検索・ジャンル・ページネーション条件に応じた書籍一覧を返す。
     *
     * @param  IndexBookRequest  $request  APIの検索条件
     * @return AnonymousResourceCollection ページネーションされた書籍一覧
     */
    public function index(
        IndexBookRequest $request
    ): AnonymousResourceCollection {
        $validated = $request->validated();

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

    /**
     * 認証ユーザーの書籍とジャンル情報を登録する。
     *
     * @param  StoreBookRequest  $request  検証済み書籍情報
     * @return JsonResponse 登録した書籍情報を含む201レスポンス
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = $request->user()->id;

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

    /**
     * 指定された書籍の詳細情報を返す。
     *
     * @param  Book  $book  表示対象の書籍
     * @return BookResource 書籍・ジャンル・レビュー情報
     */
    public function show(Book $book): BookResource
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

    /**
     * 所有者の書籍とジャンル情報を更新する。
     *
     * @param  UpdateBookRequest  $request  検証済み書籍情報
     * @param  Book  $book  更新対象の書籍
     * @return BookResource 更新後の書籍情報
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): BookResource {
        $this->authorize('update', $book);

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

    /**
     * 所有者の書籍を削除する。
     *
     * @param  Book  $book  削除対象の書籍
     * @return Response 本文を持たない204レスポンス
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
