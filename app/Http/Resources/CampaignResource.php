<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'contact_list' => new ContactListResource($this->whenLoaded('contactList')),
            'pending_count' => $this->whenHas('pending_count'),
            'sent_count' => $this->whenHas('sent_count'),
            'failed_count' => $this->whenHas('failed_count'),
            'total_count' => $this->whenHas('total_count'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
