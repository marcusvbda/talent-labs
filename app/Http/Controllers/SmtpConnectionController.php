<?php

namespace App\Http\Controllers;

use App\Actions\ConnectSmtpIntegration;
use App\Client\AccountStatusPresenter;
use App\Exceptions\SendingAccountAlreadyConnected;
use App\Exceptions\SmtpConnectionFailed;
use App\Http\Requests\ConnectSmtpRequest;
use App\Http\Resources\Client\AccountStatusResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SmtpConnectionController extends Controller
{
    public function store(
        ConnectSmtpRequest $request,
        ConnectSmtpIntegration $connect,
        AccountStatusPresenter $presenter,
    ): AccountStatusResource|JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->isActive(), 403);

        try {
            $connect->run($user, $request->settings());
        } catch (SendingAccountAlreadyConnected) {
            return $this->connectionError(__('account.email_provider.disconnect_first'));
        } catch (SmtpConnectionFailed) {
            return $this->connectionError(__('integrations.smtp.failed'));
        }

        $user->refresh();
        $user->unsetRelations();

        return new AccountStatusResource($presenter->forUser($user));
    }

    private function connectionError(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => ['connection' => [$message]],
        ], 422);
    }
}
