<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'user' => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
                'ac_no' => $this->user->ac_no,
            ]),
            'date'            => $this->assignment_date instanceof \Carbon\Carbon
                ? $this->assignment_date->toDateString()
                : $this->assignment_date,
            'type'            => $this->assignment_type,
            'shift'           => $this->whenLoaded('shift', fn () => $this->shift
                ? new ShiftResource($this->shift)
                : null
            ),
            'is_cover'        => $this->is_cover,
            'cover_for_user'  => $this->whenLoaded('coverForUser', fn () => $this->coverForUser
                ? ['id' => $this->coverForUser->id, 'name' => $this->coverForUser->name]
                : null
            ),
            'comment'         => $this->comment,
            'history_count'   => $this->relationLoaded('history') ? $this->history->count() : 0,
        ];
    }
}
