<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordChangeOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $otp
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Admin Password Change Verification Code')
            ->greeting('Admin password change request')
            ->line('Use the verification code below to confirm your password change.')
            ->line("Verification code: {$this->otp}")
            ->line('This code expires in 10 minutes.')
            ->line('If you did not request this change, keep your current password and review your account access immediately.');
    }
}
