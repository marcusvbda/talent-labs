<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives browser CSP violation reports (report-only policy) and logs a sanitized subset.
 */
class CspReportController extends Controller
{
    private const MAX_BODY_BYTES = 8192;

    private const FIELDS = [
        'document-uri',
        'blocked-uri',
        'violated-directive',
        'effective-directive',
        'source-file',
        'line-number',
    ];

    private const URI_FIELDS = ['document-uri', 'blocked-uri', 'source-file'];

    public function __invoke(Request $request): Response
    {
        // Browsers send application/csp-report, so read the raw body instead of $request->json().
        $body = $request->getContent();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $payload = json_decode($body, true);
        $report = is_array($payload) ? ($payload['csp-report'] ?? null) : null;

        if (! is_array($report)) {
            return response()->noContent();
        }

        $fields = [];

        foreach (self::FIELDS as $key) {
            $value = $report[$key] ?? null;

            if (is_int($value)) {
                $fields[$key] = $value;
            } elseif (is_string($value)) {
                // URLs may carry tokens (e.g. password reset): drop query string and fragment.
                if (in_array($key, self::URI_FIELDS, true)) {
                    $value = (string) preg_replace('/[?#].*$/s', '', $value);
                }

                $fields[$key] = Str::limit($value, 255, '');
            }
        }

        Log::warning('CSP violation', $fields);

        return response()->noContent();
    }
}
