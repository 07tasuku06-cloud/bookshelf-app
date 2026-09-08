<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /**
     * メールアドレスとパスワードを確認してAPIトークンを発行する。
     *
     * @throws ValidationException
     */
    public function store(
        StoreApiTokenRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $user = User::where(
            'email',
            $validated['email']
        )->first();

        if (
            $user === null
            || ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'メールアドレスまたはパスワードが正しくありません。',
                ],
            ]);
        }

        $token = $user
            ->createToken($validated['device_name'])
            ->plainTextToken;

        return response()->json(
            [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            Response::HTTP_CREATED
        );
    }

    /**
     * 現在のリクエストで使用したAPIトークンを失効させる。
     */
    public function destroy(Request $request): Response
    {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return response()->noContent();
    }
}
