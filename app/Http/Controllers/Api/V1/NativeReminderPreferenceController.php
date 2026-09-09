<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateNativeReminderPreferenceRequest;
use App\Http\Resources\Api\V1\NativeReminderPreferenceResource;
use App\Models\NativeReminderPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class NativeReminderPreferenceController extends Controller
{
    public function show(Request $request): NativeReminderPreferenceResource
    {
        /** @var User $user */
        $user = $request->user();

        return new NativeReminderPreferenceResource(
            $user->nativeReminderPreferences()
                ->where('personal_access_token_id', $this->sessionId($request))
                ->first() ?? new NativeReminderPreference
        );
    }

    public function update(UpdateNativeReminderPreferenceRequest $request): NativeReminderPreferenceResource
    {
        /** @var User $user */
        $user = $request->user();
        $preference = $user->nativeReminderPreferences()->updateOrCreate(
            ['personal_access_token_id' => $this->sessionId($request)],
            $request->validated(),
        );

        return new NativeReminderPreferenceResource($preference);
    }

    private function sessionId(Request $request): int
    {
        $token = $request->user()->currentAccessToken();

        abort_unless($token instanceof PersonalAccessToken, 403, 'A mobile access token is required.');

        return $token->getKey();
    }
}
