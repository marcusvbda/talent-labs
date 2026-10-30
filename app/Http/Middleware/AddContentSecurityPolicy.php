<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report-only CSP for the client app (web group). The Filament panel has its own stack and gets none.
 */
class AddContentSecurityPolicy
{
    private const HEADER = 'Content-Security-Policy-Report-Only';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Vite::isRunningHot() || $this->skipped()) {
            return $next($request);
        }

        // Before rendering, so the @vite tags of this request carry the nonce.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $connect = trim("'self' ".$this->websocketSource());

        $response->headers->set(self::HEADER, implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src {$connect}",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            'report-uri /csp-report',
        ]));

        return $response;
    }

    /**
     * Reverb endpoint the browser connects to, e.g. "wss://ws.example.com:443".
     */
    private function websocketSource(): string
    {
        $options = (array) config('reverb.apps.apps.0.options', []);
        $host = trim((string) ($options['host'] ?? ''));

        if ($host === '') {
            return '';
        }

        $scheme = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = trim((string) ($options['port'] ?? ''));

        return $scheme.'://'.$host.($port !== '' ? ':'.$port : '');
    }

    /**
     * Whether the proxy already sets this header (same skip list as AddSecurityHeaders).
     */
    private function skipped(): bool
    {
        $names = array_map(
            fn (string $name): string => strtolower(trim($name)),
            explode(',', (string) config('talent.security.headers_skip', '')),
        );

        return in_array(strtolower(self::HEADER), $names, true);
    }
}
