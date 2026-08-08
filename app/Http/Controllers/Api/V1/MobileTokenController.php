<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyMobileTokenRequest;
use App\Http\Requests\Api\V1\StoreMobileTokenRequest;
use App\Http\Resources\Api\V1\MobileTokenResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MobileTokenController extends Controller
{
    public function store(StoreMobileTokenRequest $request): MobileTokenResource
    {
        $credentials = $request->validated();

        if (! Auth::guard('web')->once([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
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
