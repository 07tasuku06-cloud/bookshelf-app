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
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BookController extends Controller
{
    /**
     * 検索条件に応じた書籍一覧を表示する。
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
     * ISBNからGoogle Books APIの書籍情報を取得する。
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
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $genres = Genre::orderBy('name')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $validated['user_id'] = auth()->id();

        $book = Book::create($validated);

        $book->genres()->sync($genreIds);

        return redirect()->route('books.show', $book);
    }

    /**
     * Display the specified resource.
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
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');

        $genres = Genre::orderBy('name')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $validated = $request->validated();

        $genreIds = $validated['genres'];
        unset($validated['genres']);

        $book->update($validated);

        $book->genres()->sync($genreIds);

        return redirect()->route('books.show', $book);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index');
    }
}
