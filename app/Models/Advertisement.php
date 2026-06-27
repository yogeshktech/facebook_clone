<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertisement extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_path',
        'cta_text',
        'plan',
        'amount',
        'payment_status',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    protected $appends = ['image_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? MediaStorage::url($this->image_path) : null;
    }

    public function getPlanLabelAttribute(): string
    {
        return match ($this->plan) {
            'monthly' => 'Monthly Plan',
            'quarterly' => 'Quarterly Plan',
            'half_yearly' => 'Half-Yearly Plan',
            'yearly' => 'Yearly Plan',
            default => ucfirst($this->plan),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'approved' && $this->expires_at && $this->expires_at->isPast()) {
            return 'Expired';
        }

        return match ($this->status) {
            'pending_payment' => 'Pending Payment',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Active',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }
}
