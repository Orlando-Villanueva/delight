<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NativeReminderPreferenceResource extends JsonResource
{
    /** @return array{enabled: bool, timezone: ?string} */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->resource->enabled,
            'timezone' => $this->resource->timezone,
        ];
    }
}
