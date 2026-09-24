<?php

namespace App\Http\Controllers;

use App\Actions\CompleteConnectedIntegration;
use App\Actions\DisconnectConnectedIntegration;
use App\Contracts\OAuthIntegrationPlugin;
use App\Enums\ConnectedIntegrationStatus;
use App\Exceptions\InvalidOAuthState;
use App\Filament\App\Pages\Preferences;
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

        return $this->startAuthorization($user, $oauthPlugin);
    }

    public function reconnect(Request $request, string $plugin): RedirectResponse
    {
        $user = $this->user($request);
        $oauthPlugin = $this->plugin($plugin);

        $existing = $this->integration($user, $plugin);
        abort_if($existing === null || $existing->status === ConnectedIntegrationStatus::Disconnected, 404);

        return $this->startAuthorization($user, $oauthPlugin);
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $this->user($request);

        try {
            $state = $this->states->consume((string) $request->query('state'), (int) $user->getKey());
            $oauthPlugin = $this->registry->get($state->pluginKey);
        } catch (InvalidOAuthState|InvalidArgumentException) {
            return $this->failed(self::FALLBACK_LABEL);
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->failed($oauthPlugin->label());
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

            return $this->failed($oauthPlugin->label());
        }

        Notification::make()
            ->title("{$oauthPlugin->label()} connected")
            ->success()
            ->send();

        return redirect()->to($this->returnUrl());
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

        return redirect()->to($this->returnUrl());
    }

    private function startAuthorization(User $user, OAuthIntegrationPlugin $oauthPlugin): RedirectResponse
    {
        $state = $this->states->issue($user, $oauthPlugin->key(), $this->returnUrl());

        return redirect()->away($oauthPlugin->authorizationUrl(
            $state['state'],
            $state['code_verifier'],
            $oauthPlugin->redirectUri(),
        ));
    }

    private function failed(string $label): RedirectResponse
    {
        Notification::make()
            ->title("{$label} connection failed")
            ->danger()
            ->send();

        return redirect()->to($this->returnUrl());
    }

    /**
     * The only place the OAuth flow ever returns to; never taken from user input.
     */
    private function returnUrl(): string
    {
        return Preferences::getUrl(panel: 'app');
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
