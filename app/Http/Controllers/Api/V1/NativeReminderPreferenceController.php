<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateNativeReminderPreferenceRequest;
use App\Http\Resources\Api\V1\NativeReminderPreferenceResource;
use App\Models\NativeReminderPreference;
use App\Models\User;
use Illuminate\Http\Request;

class NativeReminderPreferenceController extends Controller
{
    public function show(Request $request): NativeReminderPreferenceResource
    {
        /** @var User $user */
        $user = $request->user();

        return new NativeReminderPreferenceResource(
            $user->nativeReminderPreference ?? new NativeReminderPreference
        );
    }

    public function update(UpdateNativeReminderPreferenceRequest $request): NativeReminderPreferenceResource
    {
        /** @var User $user */
        $user = $request->user();
        $preference = $user->nativeReminderPreference()->updateOrCreate([], $request->validated());

        return new NativeReminderPreferenceResource($preference);
    }
}
