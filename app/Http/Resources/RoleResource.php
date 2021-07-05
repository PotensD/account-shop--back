<?php

namespace App\Http\Resources;

class RoleResource extends Resource
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
            'users' => UserResource::collection($this->whenLoaded('users')),

            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),

            'accountTypes' => AccountTypeResource::collection($this->whenLoaded('accountTypes')),
        ]);
    }
}
