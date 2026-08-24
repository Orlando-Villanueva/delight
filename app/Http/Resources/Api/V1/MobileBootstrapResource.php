<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileBootstrapResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $streaks = $this->resource['streaks'];
        $readingSummary = $this->resource['reading_summary'];

        return [
            'user' => new MobileUserResource($this->resource['user']),
            'today' => $this->resource['today'],
            'yesterday' => $this->resource['yesterday'],
            'books' => $this->resource['books'],
            'recent_book_ids' => $this->resource['recent_book_ids'],
            'has_read_today' => $this->resource['has_read_today'],
            'streak_state' => $this->resource['streak_state'],
            'current_streak' => $streaks['current_streak'],
            'longest_streak' => $streaks['longest_streak'],
            'this_week_days' => $readingSummary['this_week_days'],
            'this_month_days' => $readingSummary['this_month_days'],
            'activity' => $streaks['recent_reading_activity_series'],
        ];
    }
}
