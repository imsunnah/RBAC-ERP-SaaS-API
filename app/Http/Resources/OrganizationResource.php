<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'company'    => new CompanyResource($this->whenLoaded('company')),
            'name'       => $this->name,
            'slug'       => $this->slug,
            'logo'       => $this->logo,
            'domain'     => $this->domain,
            'timezone'   => $this->timezone,
            'currency'   => $this->currency,
            'locale'     => $this->locale,
            'status'     => $this->status,
            'settings'   => $this->settings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
