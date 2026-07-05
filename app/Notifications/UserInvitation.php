<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  Password-broker token used to accept the invite.
     */
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.create', ['token' => $this->token])
            .'?email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('You have been invited to the LMS')
            ->line('An account has been created for you.')
            ->action('Set your password', $url)
            ->line('If you did not expect this invitation, you can ignore this email.');
    }
}
