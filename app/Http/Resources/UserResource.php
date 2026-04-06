<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'nickname'   => $this->nickname,
            'ac_no'      => $this->ac_no,
            'email'      => $this->email,
            'is_admin'   => $this->is_admin,
            'department' => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'level' => $this->whenLoaded('level', fn () => [
                'id'   => $this->level->id,
                'code' => $this->level->code,
                'name' => $this->level->name,
            ]),
            'tier' => $this->whenLoaded('tier', fn () => $this->tier ? [
                'id'        => $this->tier->id,
                'tier_name' => $this->tier->tier_name,
            ] : null),
            'roles'      => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
        ];
    }
}
