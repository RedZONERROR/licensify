<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $token;
    protected Carbon $expires;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token, Carbon $expires)
    {
        $this->token = $token;
        $this->expires = $expires;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expiryMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your Password - ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('**Security Information:**')
            ->line('• Request made from IP: ' . request()->ip())
            ->line('• Request time: ' . now()->format('Y-m-d H:i:s T'))
            ->line('• This link will expire in ' . $expiryMinutes . ' minutes')
            ->action('Reset Password', $resetUrl)
            ->line('If you did not request a password reset, no further action is required. However, if you suspect unauthorized access to your account, please contact our support team immediately.')
            ->line('**Security Tips:**')
            ->line('• Never share this reset link with anyone')
            ->line('• This link can only be used once')
            ->line('• If you didn\'t request this reset, someone may be trying to access your account')
            ->salutation('Best regards,<br>' . config('app.name') . ' Security Team');
    }

    /**
     * Get the password reset URL.
     */
    protected function resetUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'password.reset',
            $this->expires,
            [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_reset',
            'expires_at' => $this->expires->toISOString(),
            'ip_address' => request()->ip(),
        ];
    }
}
