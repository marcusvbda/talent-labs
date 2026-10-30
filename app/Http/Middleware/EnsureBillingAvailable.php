<?php

namespace App\Http\Middleware;

use App\Billing\Billing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBillingAvailable
{
    /**
     * Stripe routes answer 404 unless billing is enabled and fully configured,
     * so a forged webhook never reaches Cashier without a signing secret.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Billing::available(), 404);

        return $next($request);
    }
}
