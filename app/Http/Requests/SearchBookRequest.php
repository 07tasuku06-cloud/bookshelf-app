<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchBookRequest extends FormRequest
{
    /**
     * 書籍検索はゲストを含むすべての利用者に許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 検索条件のバリデーションルールを返す。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'rating',
                    'title',
                ]),
            ],
        ];
    }

    /**
     * バリデーションエラーメッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre.integer' => 'ジャンルの指定が正しくありません。',
            'genre.exists' => '選択されたジャンルは存在しません。',
            'sort.in' => '並び順の指定が正しくありません。',
        ];
    }
}
