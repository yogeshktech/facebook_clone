<?php

namespace App\Services;

use App\Models\Advertisement;
use App\Support\AdPlans;
use Carbon\Carbon;

class AdPaymentService
{
    public function activateAfterPayment(Advertisement $ad): void
    {
        $ad->update([
            'payment_status' => 'paid',
            'status' => 'approved',
            'expires_at' => Carbon::now()->addDays(AdPlans::days($ad->plan)),
        ]);
    }

    public function markPaymentFailed(Advertisement $ad): void
    {
        $ad->update([
            'payment_status' => 'failed',
            'status' => 'payment_failed',
        ]);
    }

    public function markPaymentPending(Advertisement $ad): void
    {
        $ad->update([
            'payment_status' => 'pending',
            'status' => 'pending_payment',
        ]);
    }

    public function shouldFailPayment(string $cardNumber): bool
    {
        return str_starts_with($cardNumber, '4000');
    }
}
