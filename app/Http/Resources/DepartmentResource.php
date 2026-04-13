<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'parent_id'   => $this->parent_id,
            'path'        => $this->path,
            'is_active'   => $this->is_active,
            'children'    => DepartmentResource::collection($this->whenLoaded('children')),
            'users'       => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
