<?php

namespace App\Providers;

use App\Contracts\OAuthIntegrationPlugin;
use App\Outreach\Contracts\SendsGmailMessages;
use App\Outreach\Support\GmailApiMessageSender;
use App\Services\ConnectedIntegrationRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConnectedIntegrationRegistry::class, function (): ConnectedIntegrationRegistry {
            /** @var list<class-string<OAuthIntegrationPlugin>> $pluginClasses */
            $pluginClasses = config('connected-integrations.plugins', []);

            return new ConnectedIntegrationRegistry(array_map(
                fn (string $pluginClass): OAuthIntegrationPlugin => $this->app->make($pluginClass),
                $pluginClasses,
            ));
        });

        $this->app->bind(SendsGmailMessages::class, GmailApiMessageSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if ($this->app->runningInConsole()) {
            DevCommands::artisan('reverb:start --debug', 'reverb');

            // One independent worker per queue, so a slow queue (contacts: SMTP probing)
            // never delays another (outreach: sending). The `queue` name replaces the
            // framework's default worker, and carries the `default` queue.
            DevCommands::artisan('queue:work database --queue=default', 'queue');

            foreach (['collection', 'contacts', 'ai', 'outreach'] as $queue) {
                DevCommands::artisan("queue:work database --queue={$queue}", "queue-{$queue}");
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
