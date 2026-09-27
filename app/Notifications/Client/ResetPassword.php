<?php

namespace App\Notifications\Client;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded password reset mail. The locale comes from User::preferredLocale(),
 * which the notification sender applies automatically.
 */
class ResetPassword extends BaseResetPassword
{
    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $brand = (string) config('talent.brand.name');

        return (new MailMessage)
            ->subject(__('mail.reset.subject', ['brand' => $brand]))
            ->view('mail.client.reset-password', [
                'brand' => $brand,
                'url' => $this->resetUrl($notifiable),
                'user' => $notifiable,
                'expireMinutes' => config('auth.passwords.users.expire'),
            ]);
    }
}
