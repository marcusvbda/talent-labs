<?php

namespace App\Notifications\Client;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Branded, queued mail telling an existing account holder that someone tried to
 * sign up with their address (open mode), with a link to reset the password. The
 * locale comes from User::preferredLocale(), which the notification sender
 * applies automatically.
 */
class RegistrationAttempted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
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
            ->subject(__('mail.attempt.subject', ['brand' => $brand]))
            ->view('mail.client.registration-attempted', [
                'brand' => $brand,
                'url' => route('password.request'),
                'user' => $notifiable,
            ]);
    }
}
