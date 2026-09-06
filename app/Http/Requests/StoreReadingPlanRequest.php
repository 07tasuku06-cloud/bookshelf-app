<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * ログイン済みユーザーによる読書計画の登録を許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 読書計画登録時のバリデーションルールを返す。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
            ],
            'target_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
        ];
    }

    /**
     * バリデーションエラーの日本語メッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '書籍IDは整数で指定してください。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'target_date.required' => '期日を入力してください。',
            'target_date.date' => '期日は有効な日付を入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を入力してください。',
        ];
    }
}
