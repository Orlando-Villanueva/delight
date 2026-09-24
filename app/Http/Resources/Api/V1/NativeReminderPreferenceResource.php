<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NativeReminderPreferenceResource extends JsonResource
{
    /** @return array{enabled: bool, reading_timezone: string} */
    public function toArray(Request $request): array
    {
        return [
            'enabled' => (bool) data_get($this->resource, 'enabled'),
            'reading_timezone' => (string) data_get($this->resource, 'reading_timezone'),
        ];
    }
}
