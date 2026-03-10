<?php

namespace App\Models;

use App\Enums\CampaignSendStatus;
use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = ['subject', 'body', 'contact_list_id', 'status', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function contactList(): BelongsTo
    {
        return $this->belongsTo(ContactList::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function scopeWithSendStats($query)
    {
        return $query->withCount([
            'sends as pending_count' => fn ($q) => $q->where('status', CampaignSendStatus::Pending),
            'sends as sent_count' => fn ($q) => $q->where('status', CampaignSendStatus::Sent),
            'sends as failed_count' => fn ($q) => $q->where('status', CampaignSendStatus::Failed),
            'sends as total_count',
        ]);
    }
}
