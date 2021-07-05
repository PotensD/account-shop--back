<?php

namespace App\Http\Resources;

class AccountTypeResource extends Resource
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

            'rolesCanUsedAccountType' => RoleResource::collection($this->whenLoaded('rolesCanUsedAccountType')),

            'game' => new GameResource($this->whenLoaded('game')),

            'accountInfos' => AccountInfoResource::collection($this->whenLoaded('accountInfos')),

            'accountActions' => AccountActionResource::collection($this->whenLoaded('accountActions')),

            'accountFees' => AccountFeeResource::collection($this->whenLoaded('accountFees')),

            'accounts' => AccountResource::collection($this->whenLoaded('accounts')),
        ]);
    }
}
