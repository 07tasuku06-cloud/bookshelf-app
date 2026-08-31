<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $book = $this->route('book');

            if ($user === null || $book === null) {
                return;
            }

            $alreadyReviewed = $user->reviews()
                ->where('book_id', $book->id)
                ->exists();

            if ($alreadyReviewed) {
                $validator->errors()->add(
                    'comment',
                    'この書籍にはすでにレビューを投稿しています。'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
            'rating.integer' => '評価は整数で指定してください。',
            'rating.between' => '評価は1から5の間で選択してください。',
            'comment.required' => 'コメントを入力してください。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
