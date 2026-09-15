<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiTokenRequest extends FormRequest
{
    /**
     * トークン発行リクエストを許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * トークン発行時の入力ルールを返す。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
            'device_name' => [
                'required',
                'string',
                'max:255',
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
            'email.required' => 'メールアドレスを入力してください。',
            'email.email' => 'メールアドレスの形式が正しくありません。',
            'password.required' => 'パスワードを入力してください。',
            'password.string' => 'パスワードは文字列で入力してください。',
            'device_name.required' => '端末名を入力してください。',
            'device_name.string' => '端末名は文字列で入力してください。',
            'device_name.max' => '端末名は255文字以内で入力してください。',
        ];
    }
}
