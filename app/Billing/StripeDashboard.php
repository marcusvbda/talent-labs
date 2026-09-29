<?php

namespace App\Billing;

final class StripeDashboard
{
    public static function customerUrl(string $stripeId): string
    {
        $secret = (string) config('cashier.secret');
        $isTest = str_starts_with($secret, 'sk_test_') || str_starts_with($secret, 'rk_test_');
        $prefix = $isTest ? 'test/' : '';

        return 'https://dashboard.stripe.com/'.$prefix.'customers/'.$stripeId;
    }
}
