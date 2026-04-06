<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'name'           => $this->name,
            'hierarchy_rank' => $this->hierarchy_rank,
            'description'    => $this->description,
            'tiers'          => $this->whenLoaded('tiers', fn () => $this->tiers->map(fn ($tier) => [
                'id'          => $tier->id,
                'tier_name'   => $tier->tier_name,
                'tier_order'  => $tier->tier_order,
                'description' => $tier->description,
            ])),
          
        ];
    }
}
