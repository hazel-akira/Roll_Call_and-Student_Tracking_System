<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'status' => $this->status,
            'last_login_at' => $this->last_login_at,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ] : null,
            'identities' => $this->whenLoaded('identities', fn () => $this->identities->map(fn ($identity) => [
                'provider' => $identity->provider,
                'tenant_id' => $identity->tenant_id,
                'provider_email' => $identity->provider_email,
                'last_login_at' => $identity->last_login_at,
            ])),
        ];
    }
}
