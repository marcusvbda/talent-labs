<?php

namespace App\Actions\Users;

use App\Actions\DisconnectConnectedIntegration;
use App\Billing\Billing;
use App\Exceptions\ClientAccountDeletionBlocked;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deletes a client's account: revokes connected integrations, removes the CV folder,
 * cancels a valid Stripe subscription immediately when billing is available (failures
 * are reported, deletion continues) and deletes the user row (applications cascade,
 * invitations keep a null `used_by`).
 * Destructive — only the account owner triggers it, through `DELETE /internal/account`.
 */
final class DeleteClientAccount
{
    public function __construct(private DisconnectConnectedIntegration $disconnectConnectedIntegration) {}

    /**
     * @throws ClientAccountDeletionBlocked when the user created invitations (`invitations.created_by`
     *                                      restricts the delete); checked before anything is touched.
     */
    public function run(User $user): void
    {
        if (Invitation::query()->where('created_by', $user->id)->exists()) {
            throw new ClientAccountDeletionBlocked('This account created invitations and cannot be deleted.');
        }

        foreach ($user->connectedIntegrations as $integration) {
            try {
                $this->disconnectConnectedIntegration->run($user, $integration->plugin_key);
            } catch (Throwable $e) {
                report($e);
            }
        }

        Storage::disk('local')->deleteDirectory('cvs/'.$user->id);

        $subscription = Billing::available() ? $user->subscription('default') : null;

        if ($subscription?->valid()) {
            try {
                $subscription->cancelNow();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $user->delete();
    }
}
