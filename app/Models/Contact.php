<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'status'];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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
