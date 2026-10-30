<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if (app()->isProduction() && $request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        $skip = $this->skippedHeaders();

        foreach ($headers as $name => $value) {
            if (! in_array(strtolower($name), $skip, true)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Lowercase names of headers the proxy already sets.
     *
     * @return list<string>
     */
    private function skippedHeaders(): array
    {
        $names = array_map(
            fn (string $name): string => strtolower(trim($name)),
            explode(',', (string) config('talent.security.headers_skip', '')),
        );

        return array_values(array_filter($names, fn (string $name): bool => $name !== ''));
    }
}
