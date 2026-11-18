<?php

namespace App\Notifications;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Services\LeaveBalance;
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
            'submitted', 'submitted_for_director' => "New leave application {$a->reference} from {$a->employee_name}",
            'resubmitted' => "Leave application {$a->reference} resubmitted by {$a->employee_name}",
            'forwarded' => "Leave application {$a->reference} awaits your approval",
            'returned' => "Your leave application {$a->reference} needs more information",
            'approved' => "Your leave application {$a->reference} was approved",
            'declined' => "Your leave application {$a->reference} was declined",
            'decided' => "Leave application {$a->reference} from {$a->employee_name} was ".strtolower($a->status->label()),
            default => "Leave application {$a->reference} updated",
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->view(['emails.leave-application', 'emails.leave-application-text'], [
                'application' => $this->application,
                'recipientName' => $notifiable->name,
                'content' => $this->mailContent(),
                'details' => $this->mailDetails(),
                'comment' => $this->comment,
                'commentLabel' => $this->commentLabel(),
                'url' => route('leave.show', $this->application),
                'subject' => $this->title(),
            ]);
    }

    /**
     * Wording and colour of the email for this event and recipient.
     *
     * @return array{headline: string, intro: string, note: ?string, action: string, tone: string}
     */
    public function mailContent(): array
    {
        $a = $this->application;
        $employee = $a->employee_name;
        $director = $a->director?->name ?? 'the Director';
        $directorSubject = ucfirst($director);
        $manager = $a->manager?->name ?? 'the Manager';

        return match ($this->event) {
            'submitted' => [
                'headline' => 'New leave application to review',
                'intro' => "{$employee} has applied for leave. The application is waiting for your review before it can be forwarded to the Director.",
                'note' => null,
                'action' => 'Review application',
                'tone' => 'brand',
            ],
            'resubmitted' => [
                'headline' => 'Leave application resubmitted',
                'intro' => "{$employee} has updated and resubmitted their leave application with the information you requested.",
                'note' => null,
                'action' => 'Review application',
                'tone' => 'brand',
            ],
            'submitted_for_director' => [
                'headline' => 'New leave application submitted',
                'intro' => "{$employee} has applied for leave. This is for your information only.",
                'note' => 'The application is with the Manager for review. You will be asked for your decision once it is forwarded to you.',
                'action' => 'View application',
                'tone' => 'neutral',
            ],
            'forwarded' => [
                'headline' => 'Leave application awaits your decision',
                'intro' => $a->manager_recommendation
                    ? "{$manager} has reviewed {$employee}'s leave application and forwarded it to you for a final decision."
                    : "{$employee}'s leave application has been forwarded to you for a final decision.",
                'note' => match ($a->manager_recommendation) {
                    'approve' => 'The Manager recommends approval.',
                    null => null,
                    default => 'The Manager does not recommend approval.',
                },
                'action' => 'Review and decide',
                'tone' => 'brand',
            ],
            'returned' => [
                'headline' => 'More information needed',
                'intro' => 'Your leave application has been returned so that you can add more information. Please update it, sign the declaration again and resubmit.',
                'note' => null,
                'action' => 'Update application',
                'tone' => 'warning',
            ],
            'approved' => [
                'headline' => 'Your leave has been approved',
                'intro' => "Good news! Your leave application has been approved by {$director}.",
                'note' => $a->return_date ? 'You are expected back at work on '.$a->return_date->format('l, d F Y').'.' : null,
                'action' => 'View application',
                'tone' => 'success',
            ],
            'declined' => [
                'headline' => 'Your leave application was declined',
                'intro' => "{$directorSubject} has declined your leave application.",
                'note' => 'Please speak to your Manager if you have any questions.',
                'action' => 'View application',
                'tone' => 'danger',
            ],
            'decided' => [
                'headline' => "Leave application {$a->status->label()}",
                'intro' => "{$directorSubject} has ".strtolower($a->status->label())." {$employee}'s leave application that you forwarded.",
                'note' => null,
                'action' => 'View application',
                'tone' => $a->status === LeaveStatus::Approved ? 'success' : 'danger',
            ],
            default => [
                'headline' => 'Leave application updated',
                'intro' => "Leave application {$a->reference} has been updated.",
                'note' => null,
                'action' => 'View application',
                'tone' => 'neutral',
            ],
        };
    }

    /**
     * Rows of the application summary shown in the email.
     *
     * @return array<string, string>
     */
    public function mailDetails(): array
    {
        $a = $this->application;
        $days = LeaveBalance::days((float) $a->working_days).' working '.((float) $a->working_days === 1.0 ? 'day' : 'days');

        return array_filter([
            'Reference' => $a->reference,
            'Employee' => $a->employee_name,
            'Department / Position' => $a->departmentPosition() !== '—' ? $a->departmentPosition() : null,
            'Leave type' => $a->leaveTypeLabel(),
            'Period' => $a->start_date->format('D, d M Y').' – '.$a->end_date->format('D, d M Y'),
            'Duration' => $days,
            'Unpaid days' => $a->hasUnpaidDays() ? $a->daysSplitLabel() : null,
            'Returning on' => $a->return_date?->format('D, d M Y'),
            'Status' => $a->status->label(),
        ]);
    }

    private function commentLabel(): string
    {
        return match ($this->event) {
            'forwarded', 'returned' => 'Comment from the Manager',
            'approved', 'declined', 'decided' => 'Comment from the Director',
            default => 'Comment',
        };
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
