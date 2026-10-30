<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:production-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check that the configuration is safe for production';

    /**
     * Reads config() only and prints setting names and problems, never values.
     */
    public function handle(): int
    {
        $failures = $this->failures();
        $warnings = $this->warnings();

        if ($failures === []) {
            $this->info('Failures: none');
        } else {
            $this->error('Failures:');

            foreach ($failures as $name => $problem) {
                $this->line("  [FAIL] {$name}: {$problem}");
            }
        }

        $this->newLine();

        if ($warnings === []) {
            $this->info('Warnings: none');
        } else {
            $this->warn('Warnings:');

            foreach ($warnings as $name => $problem) {
                $this->line("  [WARN] {$name}: {$problem}");
            }
        }

        $this->newLine();

        $summary = sprintf('%d failure(s), %d warning(s).', count($failures), count($warnings));

        if ($failures !== []) {
            $this->error("Not safe for production: {$summary}");

            return self::FAILURE;
        }

        $this->info("Safe for production: {$summary}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function failures(): array
    {
        $failures = [];

        if (! app()->isProduction()) {
            $failures['APP_ENV'] = 'the environment is not production.';
        }

        if (config('app.debug')) {
            $failures['APP_DEBUG'] = 'debug mode is enabled.';
        }

        if (blank(config('app.key'))) {
            $failures['APP_KEY'] = 'the application key is not set.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $failures['APP_URL'] = 'the URL does not use https://.';
        }

        if (config('session.secure') !== true) {
            $failures['SESSION_SECURE_COOKIE'] = 'session cookies are not marked secure.';
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $failures['MAIL_MAILER'] = 'the mailer does not deliver real email.';
        }

        if (config('queue.default') === 'sync') {
            $failures['QUEUE_CONNECTION'] = 'the queue runs synchronously.';
        }

        if (config('talent.billing.enabled')) {
            $stripe = [
                'STRIPE_KEY' => 'cashier.key',
                'STRIPE_SECRET' => 'cashier.secret',
                'STRIPE_WEBHOOK_SECRET' => 'cashier.webhook.secret',
            ];

            foreach ($stripe as $name => $key) {
                if (blank(config($key))) {
                    $failures[$name] = 'billing is enabled but this Stripe setting is not set.';
                }
            }
        }

        $origins = config('reverb.apps.apps.0.allowed_origins');

        if (is_array($origins) && in_array('*', $origins, true)) {
            $failures['REVERB_ALLOWED_ORIGINS'] = 'any origin is allowed (*).';
        }

        if ($this->logsAtDebugLevel()) {
            $failures['LOG_LEVEL'] = 'the default log channel logs at debug level.';
        }

        if (blank(config('ai.providers.openai.key'))) {
            $failures['OPENAI_API_KEY'] = 'the OpenAI API key is not set.';
        }

        if (blank(config('services.google.client_id'))) {
            $failures['GOOGLE_CLIENT_ID'] = 'the Google OAuth client id is not set.';
        }

        if (blank(config('services.google.client_secret'))) {
            $failures['GOOGLE_CLIENT_SECRET'] = 'the Google OAuth client secret is not set.';
        }

        return $failures;
    }

    /**
     * @return array<string, string>
     */
    private function warnings(): array
    {
        $warnings = [];

        if (blank(config('talent.security.trusted_proxies'))) {
            $warnings['TRUSTED_PROXIES'] = 'no proxy is trusted; expected when the server is reached directly (no proxy).';
        }

        $freePrices = config('talent.plans.prices.free');

        if (is_array($freePrices)) {
            foreach (['br' => 'PRICE_FREE_BRL', 'eu' => 'PRICE_FREE_EUR', 'row' => 'PRICE_FREE_USD'] as $region => $name) {
                if (array_key_exists($region, $freePrices) && (int) $freePrices[$region] !== 0) {
                    $warnings[$name] = 'the free plan price is not 0.';
                }
            }
        }

        if (trim((string) config('filament.admin_panel_path'), '/') === 'admin') {
            $warnings['ADMIN_PANEL_PATH'] = 'the admin panel uses the default, guessable path.';
        }

        return $warnings;
    }

    /**
     * Whether the default log channel (or, for a stack, any of its channels) logs at debug level.
     */
    private function logsAtDebugLevel(): bool
    {
        $default = (string) config('logging.default');
        $channel = config("logging.channels.{$default}");

        if (! is_array($channel)) {
            return false;
        }

        $channels = ($channel['driver'] ?? null) === 'stack'
            ? (array) ($channel['channels'] ?? [])
            : [$default];

        foreach ($channels as $name) {
            if (config("logging.channels.{$name}.level") === 'debug') {
                return true;
            }
        }

        return false;
    }
}
