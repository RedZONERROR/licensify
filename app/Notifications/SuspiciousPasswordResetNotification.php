<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuspiciousPasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $ipAddress;
    protected string $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $ipAddress, string $reason)
    {
        $this->ipAddress = $ipAddress;
        $this->reason = $reason;
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
        return (new MailMessage)
            ->subject('🚨 Suspicious Password Reset Activity - ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We detected suspicious password reset activity on your account and wanted to alert you immediately.')
            ->line('**Alert Details:**')
            ->line('• Reason: ' . $this->reason)
            ->line('• IP Address: ' . $this->ipAddress)
            ->line('• Time: ' . now()->format('Y-m-d H:i:s T'))
            ->line('• User Agent: ' . request()->userAgent())
            ->line('**What this means:**')
            ->line('Someone may be attempting to gain unauthorized access to your account. If this was not you, please take immediate action.')
            ->line('**Recommended Actions:**')
            ->line('• Change your password immediately if you suspect unauthorized access')
            ->line('• Enable two-factor authentication if not already enabled')
            ->line('• Review your recent account activity')
            ->line('• Contact our support team if you need assistance')
            ->action('Secure My Account', route('profile.edit'))
            ->line('If you initiated this password reset request, you can safely ignore this email.')
            ->line('**Security Reminder:**')
            ->line('We will never ask for your password, two-factor codes, or other sensitive information via email.')
            ->salutation('Stay secure,<br>' . config('app.name') . ' Security Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'suspicious_password_reset',
            'ip_address' => $this->ipAddress,
            'reason' => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }
}
