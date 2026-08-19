<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'digits:13',

                // 更新対象の書籍自身をISBNの重複チェックから除外する
                Rule::unique('books', 'isbn')
                    ->ignore($this->route('book')),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => [
                'required',
                'integer',
                'distinct',
                'exists:genres,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attributeを入力してください。',
            'integer' => ':attributeは整数で入力してください。',
            'exists' => '指定された:attributeは存在しません。',
            'string' => ':attributeは文字列で入力してください。',
            'max.string' => ':attributeは:max文字以内で入力してください。',
            'digits' => ':attributeは:digits桁の数字で入力してください。',
            'unique' => 'この:attributeはすでに登録されています。',
            'date' => ':attributeは正しい日付で入力してください。',
            'url' => ':attributeは正しいURL形式で入力してください。',
            'array' => ':attributeは配列で入力してください。',
            'min.array' => ':attributeを:min件以上指定してください。',
            'distinct' => ':attributeが重複しています。',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ユーザーID',
            'title' => 'タイトル',
            'author' => '著者名',
            'isbn' => 'ISBN',
            'published_date' => '出版日',
            'description' => '説明',
            'image_url' => '画像URL',
            'genres' => 'ジャンル',
            'genres.*' => 'ジャンルID',
        ];
    }
}
