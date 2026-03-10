<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'status'];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', ContactStatus::Active);
    }

    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class);
    }

    public function campaignSends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }
}
