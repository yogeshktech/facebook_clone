<?php

namespace App\Support;

class AdPlans
{
    public const PLANS = [
        'monthly' => ['label' => 'Monthly', 'days' => 30, 'amount' => 999.00],
        'quarterly' => ['label' => 'Quarterly', 'days' => 90, 'amount' => 2499.00],
        'half_yearly' => ['label' => 'Half-Yearly', 'days' => 180, 'amount' => 4499.00],
        'yearly' => ['label' => 'Yearly', 'days' => 365, 'amount' => 7999.00],
    ];

    public static function amount(string $plan): float
    {
        return self::PLANS[$plan]['amount'] ?? self::PLANS['monthly']['amount'];
    }

    public static function days(string $plan): int
    {
        return self::PLANS[$plan]['days'] ?? 30;
    }

    public static function label(string $plan): string
    {
        return self::PLANS[$plan]['label'] ?? ucfirst($plan);
    }

    public static function keys(): array
    {
        return array_keys(self::PLANS);
    }
}
