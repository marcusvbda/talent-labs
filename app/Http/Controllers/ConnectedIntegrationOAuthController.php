<?php

namespace App\Http\Controllers;

use App\Actions\CompleteConnectedIntegration;
use App\Actions\DisconnectConnectedIntegration;
use App\Contracts\OAuthIntegrationPlugin;
use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\InvalidOAuthState;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Services\ConnectedIntegrationRegistry;
use App\Services\OAuthConnectionStateManager;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Throwable;

class ConnectedIntegrationOAuthController extends Controller
{
    /** Label used when an invalid state gives no trustworthy plugin key. */
    private const string FALLBACK_LABEL = 'Gmail';

    /** Accepted `?return=` keys mapped to the route the OAuth flow lands on. */
    private const RETURN_ROUTES = ['account' => 'account', 'onboarding' => 'onboarding'];

    public function __construct(
        private ConnectedIntegrationRegistry $registry,
        private OAuthConnectionStateManager $states,
        private CompleteConnectedIntegration $completeConnection,
        private DisconnectConnectedIntegration $disconnectIntegration,
    ) {}

    public function connect(Request $request, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        $oauthPlugin = $this->plugin($plugin);

        $existing = $this->integration($user, $plugin);
        abort_if($existing !== null && $existing->status !== ConnectedIntegrationStatus::Disconnected, 404);

        $this->rememberReturn($request);

        return $this->startAuthorization($request, $user, $oauthPlugin);
    }

    public function reconnect(Request $request, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        $oauthPlugin = $this->plugin($plugin);

        $existing = $this->integration($user, $plugin);
        abort_if($existing === null || $existing->status === ConnectedIntegrationStatus::Disconnected, 404);

        $this->rememberReturn($request);

        return $this->startAuthorization($request, $user, $oauthPlugin);
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $state = $this->states->consume((string) $request->query('state'), (int) $user->getKey());
            $oauthPlugin = $this->registry->get($state->pluginKey);
        } catch (InvalidOAuthState|InvalidArgumentException) {
            // No trustworthy state, so no embedded target: fall back to Account.
            return $this->failed(self::FALLBACK_LABEL, route('account'));
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->failed($oauthPlugin->label(), $state->returnUrl);
        }

        try {
            $token = $oauthPlugin->exchangeAuthorizationCode(
                (string) $request->query('code'),
                $state->codeVerifier,
                $oauthPlugin->redirectUri(),
            );
            $this->completeConnection->run($user, $state->pluginKey, $token);
        } catch (Throwable $exception) {
            $context = [
                'user_id' => $user->getKey(),
                'plugin_key' => $state->pluginKey,
                'exception_class' => $exception::class,
            ];

            if ($exception instanceof IdentityProviderException) {
                $response = $exception->getResponseBody();

                if (is_array($response)) {
                    $providerError = $response['error'] ?? null;

                    if (is_string($providerError) && preg_match('/\A[a-z0-9_]{1,64}\z/', $providerError) === 1) {
                        $context['provider_error'] = $providerError;
                    }
                }
            }

            Log::warning('Connected integration OAuth callback failed.', $context);

            return $this->failed($oauthPlugin->label(), $state->returnUrl);
        }

        Notification::make()
            ->title("{$oauthPlugin->label()} connected")
            ->success()
            ->send();

        // The target was resolved from the whitelist in connect()/reconnect() and
        // travelled inside the encrypted state; the session value is gone by now.
        return redirect()->to($state->returnUrl)
            ->with('success', __('account.gmail.connected_flash'));
    }

    public function disconnect(Request $request, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        $oauthPlugin = $this->plugin($plugin);

        $this->disconnectIntegration->run($user, $plugin);

        Notification::make()
            ->title("{$oauthPlugin->label()} disconnected")
            ->success()
            ->send();

        return redirect()->to($this->returnUrl($request))
            ->with('success', __('account.gmail.disconnected'));
    }

    private function startAuthorization(Request $request, User $user, OAuthIntegrationPlugin $oauthPlugin): RedirectResponse
    {
        $state = $this->states->issue($user, $oauthPlugin->key(), $this->returnUrl($request));

        return redirect()->away($oauthPlugin->authorizationUrl(
            $state['state'],
            $state['code_verifier'],
            $oauthPlugin->redirectUri(),
        ));
    }

    private function failed(string $label, string $returnUrl): RedirectResponse
    {
        Notification::make()
            ->title("{$label} connection failed")
            ->danger()
            ->send();

        return redirect()->to($returnUrl)
            ->with('error', __('account.gmail.failed_flash'));
    }

    /**
     * Stores the whitelisted `?return=` key; anything else (including the legacy
     * `?redirect=<url>`) resolves to `account`. The raw value is only a lookup key.
     */
    private function rememberReturn(Request $request): void
    {
        $request->session()->put('integrations.return', $this->returnKey($request->query('return')));
    }

    /** @return key-of<self::RETURN_ROUTES> */
    private function returnKey(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::RETURN_ROUTES) ? $value : 'account';
    }

    /**
     * The only place the OAuth flow ever returns to; never taken from user input
     * verbatim — the session holds a whitelist key, consumed once.
     */
    private function returnUrl(Request $request): string
    {
        return route(self::RETURN_ROUTES[$request->session()->pull('integrations.return', 'account')] ?? 'account');
    }

    private function plugin(string $key): OAuthIntegrationPlugin
    {
        try {
            return $this->registry->get($key);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }

    private function integration(User $user, string $plugin): ?ConnectedIntegration
    {
        return ConnectedIntegration::query()
            ->whereBelongsTo($user)
            ->where('plugin_key', $plugin)
            ->first();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->isActive(), 403);

        return $user;
    }
}
