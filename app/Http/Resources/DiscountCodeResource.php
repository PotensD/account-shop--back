<?php

namespace App\Http\Resources;

class DiscountCodeResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return array_merge(parent::getAttributes($request), [

            // Relationships
            'creator' => new UserResource($this->whenLoaded('creator')),
            'lastUpdatedEditor' => new UserResource($this->whenLoaded('lastUpdatedEditor')),
            'buyers' => UserResource::collection($this->whenLoaded('buyers')),

            'supportedGames' => GameResource::collection($this->whenLoaded('supportedGames')),
        ]);
    }
}
