<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Administrative reminder only. It never renews, ends or changes a contract.
 */
class ContractExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(public Contract $contract, public string $label, public array $channels = ['database', 'mail']) {}

    public function via(object $notifiable): array
    {
        return collect($this->channels)
            ->filter(fn ($c) => $c !== 'mail' || $notifiable->email)
            ->values()->all();
    }

    public function title(): string
    {
        $c = $this->contract;
        $days = $c->daysRemaining();
        $when = match (true) {
            $days > 0 => "expires in {$days} days ({$c->end_date->format('d M Y')})",
            $days === 0 => 'expires today',
            default => 'expired on '.$c->end_date->format('d M Y').' and has no recorded decision',
        };

        return "{$this->label}: contract for {$c->full_name} {$when}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contract;

        return (new MailMessage)
            ->subject('Contract reminder: '.$c->full_name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->title().'.')
            ->line("Contract {$c->reference} ({$c->typeLabel()}), {$c->start_date->format('d M Y')} to {$c->end_date->format('d M Y')}.")
            ->line('Please start the renewal or non-renewal process in line with LivityICT procedures and record the decision in the HRMS.')
            ->action('Open contract', route('contracts.show', $c))
            ->line('This is an administrative reminder. The system does not renew or end contracts automatically.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => null,
            'url' => route('contracts.show', $this->contract, false),
            'icon' => 'contract',
            'event' => 'contract_reminder',
        ];
    }
}
