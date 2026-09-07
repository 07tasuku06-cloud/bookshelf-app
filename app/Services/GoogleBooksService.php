<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleBooksService
{
    /**
     * ISBNを使ってGoogle Books APIから書籍情報を取得する。
     *
     * @return array{
     *     title: string,
     *     author: string,
     *     published_date: string|null,
     *     description: string|null,
     *     image_url: string|null
     * }|null
     */
    public function searchByIsbn(string $isbn): ?array
    {
        $parameters = [
            'q' => "isbn:{$isbn}",
            'maxResults' => 1,
        ];

        $apiKey = config('services.google_books.key');

        if (is_string($apiKey) && $apiKey !== '') {
            $parameters['key'] = $apiKey;
        }

        $response = Http::acceptJson()
            ->timeout(5)
            ->get(
                (string) config(
                    'services.google_books.url',
                    'https://www.googleapis.com/books/v1/volumes'
                ),
                $parameters
            );

        $response->throw();

        $volumeInfo = $response->json('items.0.volumeInfo');

        if (! is_array($volumeInfo)) {
            return null;
        }

        $authors = $volumeInfo['authors'] ?? [];

        return [
            'title' => is_string($volumeInfo['title'] ?? null)
                ? $volumeInfo['title']
                : '',
            'author' => collect(is_array($authors) ? $authors : [])
                ->filter(
                    fn (mixed $author): bool => is_string($author)
                )
                ->implode('、'),
            'published_date' => $this->normalizePublishedDate(
                $volumeInfo['publishedDate'] ?? null
            ),
            'description' => is_string($volumeInfo['description'] ?? null)
                ? $volumeInfo['description']
                : null,
            'image_url' => $this->normalizeImageUrl(
                data_get($volumeInfo, 'imageLinks.thumbnail')
            ),
        ];
    }

    /**
     * Google Booksの出版日をHTMLの日付入力形式へ統一する。
     */
    private function normalizePublishedDate(mixed $publishedDate): ?string
    {
        if (! is_string($publishedDate)) {
            return null;
        }

        return match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedDate) === 1 => $publishedDate,
            preg_match('/^\d{4}-\d{2}$/', $publishedDate) === 1 => "{$publishedDate}-01",
            preg_match('/^\d{4}$/', $publishedDate) === 1 => "{$publishedDate}-01-01",
            default => null,
        };
    }

    /**
     * Google Booksの画像URLをHTTPSへ統一する。
     */
    private function normalizeImageUrl(mixed $imageUrl): ?string
    {
        if (! is_string($imageUrl) || $imageUrl === '') {
            return null;
        }

        if (str_starts_with($imageUrl, 'http://')) {
            return 'https://'.substr($imageUrl, 7);
        }

        return $imageUrl;
    }
}
