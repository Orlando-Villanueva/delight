<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingLogDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date_read' => $this->resource->first()->date_read->toDateString(),
            'groups' => ReadingLogGroupResource::collection($this->resource),
        ];
    }
}
