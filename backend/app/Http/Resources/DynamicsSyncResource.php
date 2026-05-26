<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DynamicsSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendance_session_id' => $this->attendance_session_id,
            'status' => $this->status,
            'direction' => $this->direction,
            'integration' => $this->integration,
            'external_reference' => $this->external_reference,
            'error_message' => $this->error_message,
            'retries' => $this->retries,
            'synced_at' => $this->synced_at,
            'created_at' => $this->created_at,
        ];
    }
}
