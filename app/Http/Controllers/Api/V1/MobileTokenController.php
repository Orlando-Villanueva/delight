<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyMobileTokenRequest;
use App\Http\Requests\Api\V1\StoreMobileTokenRequest;
use App\Http\Resources\Api\V1\MobileTokenResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileTokenController extends Controller
{
    public function store(StoreMobileTokenRequest $request): MobileTokenResource
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($credentials['device_name'], ['mobile']);

        return new MobileTokenResource([
            'plain_text_token' => $token->plainTextToken,
            'user' => $user,
        ]);
    }

    public function destroy(DestroyMobileTokenRequest $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
