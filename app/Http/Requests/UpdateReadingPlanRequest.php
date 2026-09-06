<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * 読書計画の認可はControllerからPolicyを使用して判定する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 読書計画更新時のバリデーションルールを返す。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
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
            'target_date.required' => '期日を入力してください。',
            'target_date.date' => '期日は有効な日付を入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を入力してください。',
        ];
    }
}
