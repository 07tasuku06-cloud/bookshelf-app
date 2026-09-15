<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FetchBookByIsbnRequest extends FormRequest
{
    /**
     * ISBN検索を許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * URL内のISBNをバリデーション対象へ追加する。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'isbn' => $this->route('isbn'),
        ]);
    }

    /**
     * ISBN検索のバリデーションルールを返す。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
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
            'isbn.required' => 'ISBNを入力してください。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
        ];
    }

    /**
     * Bladeが扱えるJSON形式でバリデーションエラーを返す。
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(
                [
                    'error' => $validator->errors()->first('isbn'),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            )
        );
    }
}
