<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    /**
     * $this->resource is expected to be an array with shape:
     * [
     *   'user' => User,
     *   'days' => [ ['date'=>..., 'status'=>..., 'clock_in'=>..., ...], ... ]
     * ]
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $days = $this->resource['days'];

        return [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'ac_no' => $user->ac_no,
            ],
            'days' => collect($days)->map(fn ($day) => [
                'date'          => $day['date'],
                'status'        => $day['status'],
                'clock_in'      => $day['clock_in'],
                'clock_out'     => $day['clock_out'],
                'late_minutes'  => $day['late_minutes'],
                'early_minutes' => $day['early_minutes'],
                'shift'         => isset($day['shift']) && $day['shift'] ? [
                    'id'         => $day['shift']->id,
                    'name'       => $day['shift']->name,
                    'start_time' => $day['shift']->start_time,
                    'end_time'   => $day['shift']->end_time,
                ] : null,
            ])->values(),
        ];
    }
}
