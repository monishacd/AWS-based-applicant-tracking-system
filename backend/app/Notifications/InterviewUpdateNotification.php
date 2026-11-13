<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InterviewUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Interview $interview,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $jobTitle = $this->interview->application->job->title;
        $scheduledAt = $this->interview->scheduled_at->toIso8601String();
        $message = match ($this->event) {
            'scheduled' => "An interview for {$jobTitle} has been scheduled.",
            'rescheduled' => "Your interview for {$jobTitle} has been rescheduled.",
            'cancelled' => "Your interview for {$jobTitle} has been cancelled.",
            'outcome_published' => "An outcome for your interview for {$jobTitle} has been published.",
            default => "Your interview for {$jobTitle} has been updated.",
        };

        return [
            'event' => $this->event,
            'interview_id' => $this->interview->id,
            'job_title' => $jobTitle,
            'scheduled_at' => $scheduledAt,
            'message' => $message,
        ];
    }
}
