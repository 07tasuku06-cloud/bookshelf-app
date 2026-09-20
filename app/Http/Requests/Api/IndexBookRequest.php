<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
{
    /**
     * 誰でも書籍一覧APIを利用できる。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 書籍一覧の検索条件を検証する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => [
                'nullable',
                'string',
                'max:255',
            ],
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
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * 日本語のバリデーションメッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre_id.integer' => 'ジャンルIDは整数で入力してください。',
            'genre_id.exists' => '指定されたジャンルは存在しません。',
            'per_page.integer' => '1ページの件数は整数で入力してください。',
            'per_page.min' => '1ページの件数は1件以上で指定してください。',
            'per_page.max' => '1ページの件数は100件以下で指定してください。',
            'page.integer' => 'ページ番号は整数で入力してください。',
            'page.min' => 'ページ番号は1以上で指定してください。',
        ];
    }
}
