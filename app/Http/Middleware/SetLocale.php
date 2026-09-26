<?php

namespace App\Http\Middleware;

use App\Support\I18n\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(LocaleResolver::resolve($request));

        return $next($request);
    }
}
