<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'week_start'   => $this->week_start instanceof \Carbon\Carbon
                ? $this->week_start->toDateString()
                : $this->week_start,
            'status'       => $this->status,
            'published_at' => $this->published_at,
            'published_by' => $this->whenLoaded('publisher', fn () => $this->publisher
                ? ['id' => $this->publisher->id, 'name' => $this->publisher->name]
                : null
            ),
            'department'   => $this->whenLoaded('department', fn () => $this->department
                ? ['id' => $this->department->id, 'name' => $this->department->name]
                : null
            ),
            'assignments'  => ShiftAssignmentResource::collection(
                $this->whenLoaded('assignments')
            ),
        ];
    }
}
