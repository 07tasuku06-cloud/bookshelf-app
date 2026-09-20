<?php

namespace App\Http\Controllers;

use App\Http\Requests\FetchBookByIsbnRequest;
use App\Http\Requests\SearchBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BookController extends Controller
{
    /**
     * 検索・ジャンル・並び順の条件に応じた書籍一覧を表示する。
     *
     * @param  SearchBookRequest  $request  検証済み検索条件
     * @return View 書籍一覧画面
     */
    public function index(SearchBookRequest $request): View
    {
        $validated = $request->validated();

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $genreId = isset($validated['genre'])
            ? (int) $validated['genre']
            : null;
        $sort = $validated['sort'] ?? 'newest';

        $query = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->when(
                $keyword !== '',
                function (Builder $query) use ($keyword): void {
                    $query->where(
                        function (Builder $query) use ($keyword): void {
                            $query->where('title', 'like', "%{$keyword}%")
                                ->orWhere('author', 'like', "%{$keyword}%");
                        }
                    );
                }
            )
            ->when(
                $genreId !== null,
                function (Builder $query) use ($genreId): void {
                    $query->whereHas(
                        'genres',
                        function (Builder $query) use ($genreId): void {
                            $query->where('genres.id', $genreId);
                        }
                    );
                }
            );

        match ($sort) {
            'oldest' => $query->oldest(),
            'rating' => $query
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('id'),
            'title' => $query
                ->orderBy('title')
                ->orderBy('id'),
            default => $query->latest(),
        };

        $books = $query
            ->paginate(10)
            ->withQueryString();

        $genres = Genre::orderBy('name')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * ISBNを使ってGoogle Books APIから書籍情報を取得する。
     *
     * @param  FetchBookByIsbnRequest  $request  検証済みISBN
     * @param  GoogleBooksService  $googleBooksService  書籍情報取得サービス
     * @return JsonResponse 取得した書籍情報またはエラー情報
     */
    public function fetchByIsbn(
        FetchBookByIsbnRequest $request,
        GoogleBooksService $googleBooksService
    ): JsonResponse {
        try {
            $bookData = $googleBooksService->searchByIsbn(
                (string) $request->validated('isbn')
            );
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Google Books APIとの通信に失敗しました。', [
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            return response()->json(
                [
                    'error' => '書籍情報の取得に失敗しました。',
                ],
                Response::HTTP_BAD_GATEWAY
            );
        }

        if ($bookData === null) {
            return response()->json(
                [
                    'error' => '該当する書籍が見つかりませんでした。',
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return response()->json($bookData);
    }

    /**
     * 書籍登録画面を表示する。
     *
     * @return View 書籍登録画面
     */
    public function create(): View
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 認証ユーザーの書籍とジャンル情報を登録する。
     *
     * @param  StoreBookRequest  $request  検証済み書籍情報
     * @return RedirectResponse 書籍詳細画面へのリダイレクト
     */
    public function store(
        StoreBookRequest $request
    ): RedirectResponse {
        $validated = $request->validated();

        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $validated['user_id'] = auth()->id();

        $book = DB::transaction(
            function () use ($validated, $genreIds): Book {
                $book = Book::create($validated);

                $book->genres()->sync($genreIds);

                return $book;
            }
        );

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 指定された書籍とジャンル・レビュー情報を表示する。
     *
     * @param  Book  $book  表示対象の書籍
     * @return View 書籍詳細画面
     */
    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    /**
     * 所有者に指定された書籍の編集画面を表示する。
     *
     * @param  Book  $book  編集対象の書籍
     * @return View 書籍編集画面
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');

        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 所有者の書籍とジャンル情報を更新する。
     *
     * @param  UpdateBookRequest  $request  検証済み書籍情報
     * @param  Book  $book  更新対象の書籍
     * @return RedirectResponse 書籍詳細画面へのリダイレクト
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): RedirectResponse {
        $this->authorize('update', $book);

        $validated = $request->validated();

        $genreIds = $validated['genres'];
        unset($validated['genres']);

        DB::transaction(
            function () use ($book, $validated, $genreIds): void {
                $book->update($validated);

                $book->genres()->sync($genreIds);
            }
        );

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 所有者の書籍を削除する。
     *
     * @param  Book  $book  削除対象の書籍
     * @return RedirectResponse 書籍一覧画面へのリダイレクト
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
