<?php

namespace App\Providers;

use App\Contracts\OAuthIntegrationPlugin;
use App\Outreach\Support\OutreachConfig;
use App\Plans\PlanCatalog;
use App\Services\ConnectedIntegrationRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

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

        $this->app->singleton(PlanCatalog::class);

        // Re-registered in routes/cashier.php behind EnsureBillingAvailable.
        Cashier::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Set here, not in bootstrap/app.php: that closure runs before config loads.
        // The middleware accepts `*` or a comma list string as-is.
        if (filled($trustedProxies = config('talent.security.trusted_proxies'))) {
            TrustProxies::at($trustedProxies);
        }

        // Fail fast on unsafe sending knobs, in every environment.
        OutreachConfig::validate();

        // Named so the key is the IP even for signed-in users.
        RateLimiter::for('locale', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Client JSON contracts have no envelope.
        JsonResource::withoutWrapping();

        // Keep the paid plan while Stripe retries a failed renewal (dunning).
        Cashier::keepPastDueSubscriptionsActive();

        if ($this->app->runningInConsole()) {
            DevCommands::artisan('reverb:start --debug', 'reverb');

            // One independent worker per queue, so a slow queue (contacts: SMTP probing)
            // never delays another (outreach: sending). The `queue` name replaces the
            // framework's default worker, and carries the `default` queue.
            DevCommands::artisan('queue:work database --queue=default', 'queue');

            foreach (['collection', 'contacts', 'ai', 'outreach'] as $queue) {
                DevCommands::artisan("queue:work database --queue={$queue}", "queue-{$queue}");
            }

            DevCommands::artisan('schedule:work', 'scheduler');
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
