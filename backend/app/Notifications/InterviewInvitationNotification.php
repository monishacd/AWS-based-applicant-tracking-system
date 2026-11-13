<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Job $job,
        private readonly User $recruiter,
        private readonly string $scheduledAt,
        private readonly ?string $meetingDetails,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $companyName = $this->recruiter->company_name ?: $this->recruiter->name;

        $message = (new MailMessage)
            ->subject("Interview invitation: {$this->job->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$companyName} has invited you to interview for {$this->job->title}.")
            ->line('Scheduled for: '.$this->scheduledAt);

        if ($this->meetingDetails) {
            $message->line('Meeting details: '.$this->meetingDetails);
        }

        return $message
            ->line('Sign in to HireFlow to review your application and interview details.')
            ->salutation("Regards,\n{$companyName}");
    }
}
