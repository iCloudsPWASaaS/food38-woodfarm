<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'provider'       => $this->provider,
            'client_id'      => $this->client_id,
            'client_secret'  => $this->client_secret,
            'api_key'        => $this->api_key,
            'store_id'       => $this->store_id,
            'webhook_secret' => $this->webhook_secret,
            'is_live'        => (bool) $this->is_live,
        ];
    }
}