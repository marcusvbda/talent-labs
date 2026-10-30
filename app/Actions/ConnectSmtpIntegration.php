<?php

namespace App\Actions;

use App\Data\SmtpSettings;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\SendingPauseReason;
use App\Exceptions\SendingAccountAlreadyConnected;
use App\Exceptions\SmtpConnectionFailed;
use App\Models\ConnectedIntegration;
use App\Models\User;
use App\Outreach\Support\SendScheduler;
use App\Outreach\Support\Smtp\SmtpTransportFactory;
use App\Services\ConnectedIntegrationRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * Tests a user's SMTP details (connect, TLS, AUTH, login; nothing is sent) and stores
 * them as the user's Connected `smtp` integration.
 */
class ConnectSmtpIntegration
{
    public const string PLUGIN_KEY = 'smtp';

    public function __construct(
        private ConnectedIntegrationRegistry $registry,
        private SmtpTransportFactory $factory,
        private SendScheduler $scheduler,
    ) {}

    /**
     * @throws SendingAccountAlreadyConnected when another sending account is active.
     * @throws SmtpConnectionFailed when the connection test fails.
     */
    public function run(User $user, SmtpSettings $settings): ConnectedIntegration
    {
        // Fail fast before the (up to 20 s) network test; the check is repeated under the lock below.
        $this->ensureNoOtherSendingAccount($user);

        // Outside any transaction, so the user row is not locked during the network test.
        $this->testConnection($user, $settings);

        $integration = DB::transaction(function () use ($user, $settings): ConnectedIntegration {
            // Serialises concurrent connects so the single-sending-account rule holds.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $this->ensureNoOtherSendingAccount($user);

            $integration = ConnectedIntegration::query()->firstOrNew([
                'user_id' => $user->getKey(),
                'plugin_key' => self::PLUGIN_KEY,
            ]);

            $integration->fill([
                'status' => ConnectedIntegrationStatus::Connected,
                'external_account_id' => $settings->username,
                'account_email' => $settings->fromAddress,
                'account_name' => $settings->displayName,
                'credentials' => ['username' => $settings->username, 'password' => $settings->password],
                'metadata' => [
                    'preset' => $settings->preset,
                    'host' => $settings->host,
                    'port' => $settings->port,
                    'security' => $settings->security,
                ],
                'connected_at' => now(),
                'last_error_at' => null,
            ])->save();

            return $integration;
        });

        // Reconnecting clears a pause caused by the broken email authorization.
        if ($user->fresh()?->sending_pause_reason === SendingPauseReason::ReauthorizationRequired) {
            $this->scheduler->resume($user);
        }

        return $integration;
    }

    /**
     * Same rule as CompleteConnectedIntegration. `smtp` is excluded explicitly, so the
     * check holds whether or not the SMTP plugin is registered.
     */
    private function ensureNoOtherSendingAccount(User $user): void
    {
        $otherKeys = array_values(array_diff($this->registry->sendingPluginKeys(), [self::PLUGIN_KEY]));

        if ($otherKeys === []) {
            return;
        }

        $otherSending = ConnectedIntegration::query()
            ->whereBelongsTo($user)
            ->whereIn('plugin_key', $otherKeys)
            ->where('status', '!=', ConnectedIntegrationStatus::Disconnected)
            ->exists();

        if ($otherSending) {
            throw new SendingAccountAlreadyConnected;
        }
    }

    /**
     * Never logs or chains the Symfony exception: its message can contain the username and server text.
     */
    private function testConnection(User $user, SmtpSettings $settings): void
    {
        $transport = null;

        try {
            $transport = $this->factory->make($settings->host, $settings->port, $settings->username, $settings->password);
            $this->factory->startAuthenticated($transport);
        } catch (Throwable $exception) {
            Log::warning('SMTP connection test failed.', [
                'user_id' => $user->getKey(),
                'plugin_key' => self::PLUGIN_KEY,
                'code' => (int) $exception->getCode(),
                'exception' => $exception::class,
            ]);

            throw new SmtpConnectionFailed;
        } finally {
            $this->stop($transport);
        }
    }

    private function stop(?EsmtpTransport $transport): void
    {
        try {
            $transport?->stop();
        } catch (Throwable) {
            // Closing a half-open connection must not change the outcome.
        }
    }
}
