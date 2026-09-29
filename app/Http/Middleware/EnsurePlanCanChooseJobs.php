<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Plans\PlanCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanCanChooseJobs
{
    public function __construct(private PlanCatalog $plans) {}

    /**
     * Only plans that let the user choose jobs reach posting-by-choice endpoints.
     * The plan is read on every request, so a downgrade takes effect immediately.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->plans->for($user)->canChooseJobs()) {
            return $next($request);
        }

        if ($request->is('internal/*') || $request->expectsJson()) {
            abort(403, __('queue.mode.select_plans_only'));
        }

        return redirect()->route('dashboard');
    }
}
