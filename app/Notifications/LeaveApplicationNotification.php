<?php

namespace App\Notifications;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(public LeaveApplication $application, public string $event, public ?string $comment = null) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        $a = $this->application;

        return match ($this->event) {
            'submitted' => "New leave application {$a->reference} from {$a->employee_name}",
            'resubmitted' => "Leave application {$a->reference} resubmitted by {$a->employee_name}",
            'forwarded' => "Leave application {$a->reference} awaits your approval",
            'returned' => "Your leave application {$a->reference} needs more information",
            'approved' => "Your leave application {$a->reference} was approved",
            'declined' => "Your leave application {$a->reference} was declined",
            'decided' => "Leave application {$a->reference} was {$a->status->label()}",
            default => "Leave application {$a->reference} updated",
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->application;
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->title().'.')
            ->line("Leave: {$a->category->name}, {$a->start_date->format('d M Y')} to {$a->end_date->format('d M Y')} ({$a->working_days} working days).");

        if ($this->comment) {
            $mail->line('Comment: '.$this->comment);
        }

        return $mail->action('View application', route('leave.show', $a))
            ->line('This is an automated message from the LivityICT HRMS.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->comment,
            'url' => route('leave.show', $this->application, false),
            'icon' => 'leave',
            'event' => $this->event,
        ];
    }
}
