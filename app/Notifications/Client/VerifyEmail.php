<?php

namespace App\Notifications\Client;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded, queued email verification mail. The locale comes from
 * User::preferredLocale(), which the notification sender applies automatically.
 */
class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('mail.verify.subject', ['brand' => $brand]))
            ->view('mail.client.verify-email', [
                'brand' => $brand,
                'url' => $this->verificationUrl($notifiable),
                'user' => $notifiable,
                'expireMinutes' => config('auth.verification.expire', 60),
            ]);
    }
}
