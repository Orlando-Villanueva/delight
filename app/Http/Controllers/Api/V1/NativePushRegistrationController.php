<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNativePushRegistrationRequest;
use App\Models\NativePushRegistration;
use App\Services\NativePushRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class NativePushRegistrationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $registered = NativePushRegistration::query()
            ->where('personal_access_token_id', $this->session($request)->getKey())->exists();

        return response()->json(['data' => ['registered' => $registered]]);
    }

    public function update(StoreNativePushRegistrationRequest $request, NativePushRegistrationService $registrations): JsonResponse
    {
        $registrations->register($this->session($request), $request->validated('expo_push_token'));

        return response()->json(['data' => ['registered' => true]]);
    }

    public function destroy(Request $request): Response
    {
        NativePushRegistration::query()->where('personal_access_token_id', $this->session($request)->getKey())->delete();

        return response()->noContent();
    }

    private function session(Request $request): PersonalAccessToken
    {
        $session = $request->user()->currentAccessToken();
        abort_unless($session instanceof PersonalAccessToken, 403, 'A mobile access token is required.');

        return $session;
    }
}
