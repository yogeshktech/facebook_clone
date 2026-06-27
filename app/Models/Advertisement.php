<?php

namespace App\Models;

use App\Support\AdPlans;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
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

    protected $appends = ['image_url', 'campaign_status_label', 'payment_status_label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->where('payment_status', 'paid')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isRunning(): bool
    {
        return $this->status === 'approved'
            && $this->payment_status === 'paid'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function canAcceptLeads(): bool
    {
        return $this->isRunning();
    }

    public function needsPayment(): bool
    {
        return in_array($this->payment_status, ['pending', 'failed'], true)
            || in_array($this->status, ['pending_payment', 'payment_failed'], true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? MediaStorage::url($this->image_path) : null;
    }

    public function getPlanLabelAttribute(): string
    {
        return AdPlans::label($this->plan).' Plan';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'failed' => 'Payment Failed',
            default => 'Payment Pending',
        };
    }

    public function getCampaignStatusLabelAttribute(): string
    {
        if ($this->payment_status === 'failed' || $this->status === 'payment_failed') {
            return 'Payment Failed — Not Running';
        }

        if ($this->payment_status === 'pending' || $this->status === 'pending_payment') {
            return 'Payment Pending — Not Running';
        }

        if ($this->status === 'rejected') {
            return 'Rejected — Not Running';
        }

        if ($this->status === 'approved' && $this->expires_at?->isPast()) {
            return 'Expired — Not Running';
        }

        if ($this->isRunning()) {
            return 'Running';
        }

        return 'Not Running';
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->campaign_status_label;
    }
}
