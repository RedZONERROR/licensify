<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected int $lockDurationMinutes;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $lockDurationMinutes)
    {
        $this->lockDurationMinutes = $lockDurationMinutes;
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
        $lockDurationText = $this->formatLockDuration($this->lockDurationMinutes);

        return (new MailMessage)
            ->subject('🔒 Account Temporarily Locked - ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your account has been temporarily locked due to multiple failed login attempts.')
            ->line('**Lock Details:**')
            ->line('• Lock Duration: ' . $lockDurationText)
            ->line('• Locked At: ' . now()->format('Y-m-d H:i:s T'))
            ->line('• IP Address: ' . request()->ip())
            ->line('• Failed Attempts: 5 or more')
            ->line('**What this means:**')
            ->line('To protect your account from unauthorized access, we have temporarily locked it after detecting multiple failed login attempts.')
            ->line('**What you can do:**')
            ->line('• Wait for the lock period to expire, then try logging in again')
            ->line('• If you forgot your password, use the password reset feature')
            ->line('• If you suspect unauthorized access, contact our support team')
            ->line('• Consider enabling two-factor authentication for added security')
            ->action('Reset Password', route('password.request'))
            ->line('**Security Tips:**')
            ->line('• Use a strong, unique password for your account')
            ->line('• Enable two-factor authentication')
            ->line('• Never share your login credentials')
            ->line('• Log out from shared or public computers')
            ->salutation('Stay secure,<br>' . config('app.name') . ' Security Team');
    }

    /**
     * Format lock duration for human readability
     */
    protected function formatLockDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        $text = $hours . ' hour' . ($hours !== 1 ? 's' : '');
        
        if ($remainingMinutes > 0) {
            $text .= ' and ' . $remainingMinutes . ' minute' . ($remainingMinutes !== 1 ? 's' : '');
        }

        return $text;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_locked',
            'lock_duration_minutes' => $this->lockDurationMinutes,
            'locked_at' => now()->toISOString(),
            'ip_address' => request()->ip(),
        ];
    }
}
