<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractReminder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ContractExpiryNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Contract monitoring. Reminders are administrative only: the system never
 * renews, terminates or changes contract terms on its own.
 */
class ContractService
{
    public function nextReference(): string
    {
        $prefix = 'CT-'.now()->format('Y').'-';
        $last = Contract::where('reference', 'like', $prefix.'%')->max('reference');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** Status implied by the dates for a contract that is being monitored. */
    public function monitoredStatusFor(Contract $c): ContractStatus
    {
        $days = $c->daysRemaining();
        $window = (int) Setting::get('contract_due_to_expire_days');

        return match (true) {
            $days < 0 => ContractStatus::Expired,
            $days <= $window => ContractStatus::DueToExpire,
            default => ContractStatus::Active,
        };
    }

    /** Move monitored contracts between Active / Due to Expire / Expired. */
    public function refreshStatuses(): int
    {
        $changed = 0;
        Contract::monitored()->each(function (Contract $c) use (&$changed) {
            $status = $this->monitoredStatusFor($c);
            if ($status !== $c->status) {
                $c->update(['status' => $status]);
                $changed++;
            }
        });

        return $changed;
    }

    public function storeDocument(Contract $contract, UploadedFile $file, string $type, ?User $by): ContractDocument
    {
        $path = $file->store('contracts/'.$contract->id, 'local');

        $previous = null;
        if ($type === 'signed_contract') {
            $previous = $contract->documents()->where('document_type', 'signed_contract')->where('is_current', true)->first();
            $previous?->update(['is_current' => false]);
        }

        $doc = $contract->documents()->create([
            'document_type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $by?->id,
            'replaces_id' => $previous?->id,
            'is_current' => true,
        ]);

        AuditLog::record($previous ? 'contract.document_replaced' : 'contract.document_uploaded', $contract,
            ($previous ? 'Replaced ' : 'Uploaded ').$doc->typeLabel().' "'.$doc->original_name.'" on '.$contract->reference);

        return $doc;
    }

    /**
     * Record a renewal or extension. Creates a new contract version and keeps
     * the previous one (status Renewed or Superseded) in the history.
     */
    public function renew(Contract $old, array $data, ?UploadedFile $document, User $by): Contract
    {
        return DB::transaction(function () use ($old, $data, $document, $by) {
            $isExtension = $data['renewal_kind'] === 'extension';

            $new = Contract::create([
                'reference' => $this->nextReference(),
                'user_id' => $old->user_id,
                'full_name' => $old->full_name,
                'employee_number' => $old->employee_number,
                'job_title' => $data['job_title'] ?? $old->job_title,
                'department_id' => $old->department_id,
                'contract_type' => $data['contract_type'] ?? $old->contract_type,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'date_signed' => $data['date_signed'] ?? null,
                'commencement_date' => $data['commencement_date'] ?? null,
                'status' => ContractStatus::Active,
                'dates_verified' => true,
                'previous_contract_id' => $old->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $new->update(['status' => $this->monitoredStatusFor($new)]);

            $old->update([
                'status' => $isExtension ? ContractStatus::Superseded : ContractStatus::Renewed,
                'renewal_decision' => 'renew',
                'renewal_decided_by_name' => $data['decided_by_name'],
                'renewal_decision_date' => $data['decision_date'],
                'renewal_notes' => $data['notes'] ?? null,
                'renewal_recorded_by' => $by->id,
            ]);

            if ($document) {
                $this->storeDocument($new, $document, $isExtension ? 'extension' : 'renewal', $by);
            }

            AuditLog::record($isExtension ? 'contract.extended' : 'contract.renewed', $old,
                "{$old->reference} ".($isExtension ? 'extended' : 'renewed')." as {$new->reference} ({$new->start_date->format('d M Y')} to {$new->end_date->format('d M Y')})");

            return $new;
        });
    }

    /** Record a non-renewal / contract end decision made through management. */
    public function end(Contract $c, array $data, User $by): void
    {
        $c->update([
            'status' => ContractStatus::Ended,
            'renewal_decision' => 'not_renew',
            'renewal_decided_by_name' => $data['decided_by_name'],
            'renewal_decision_date' => $data['decision_date'],
            'renewal_notes' => $data['notes'] ?? null,
            'renewal_recorded_by' => $by->id,
            'ended_on' => $data['ended_on'],
        ]);
        AuditLog::record('contract.ended', $c, "{$c->reference} recorded as not renewed / ended on {$c->ended_on->format('d M Y')}");
    }

    /**
     * Generate due reminders. Returns the number of reminders generated.
     */
    public function sendReminders(): int
    {
        $this->refreshStatuses();
        $managers = User::active()->withRole(Role::Manager)->get();
        $channels = array_values(array_intersect(['mail', 'database'], explode(',', (string) Setting::get('contract_notify_channels'))));
        $channels = $channels ?: ['database'];
        $thresholds = Setting::reminderDays();
        $followUpEvery = max(1, (int) Setting::get('contract_followup_interval_days'));
        $count = 0;

        foreach (Contract::monitored()->with('reminders')->get() as $contract) {
            $days = $contract->daysRemaining();
            // Only reminders that belong to the current end date count (the date may have been corrected).
            $end = $contract->end_date->toDateString();
            $sentKeys = $contract->reminders->filter(function ($r) use ($end) {
                if (str_starts_with($r->reminder_key, 'reminder_')) {
                    return $r->scheduled_for->copy()->addDays((int) substr($r->reminder_key, 9))->toDateString() === $end;
                }

                return $r->reminder_key !== 'expiry' || $r->scheduled_for->toDateString() === $end;
            })->pluck('reminder_key')->all();

            if ($days > 0) {
                // The tightest threshold reached that has not yet been sent.
                $due = collect($thresholds)->filter(fn ($t) => $days <= $t)->sort()->first();
                if ($due !== null && ! in_array("reminder_$due", $sentKeys, true)) {
                    // Skip larger thresholds that were missed (e.g. contract captured late).
                    foreach ($thresholds as $t) {
                        if ($t > $due && ! in_array("reminder_$t", $sentKeys, true)) {
                            $contract->reminders()->create([
                                'reminder_key' => "reminder_$t", 'scheduled_for' => $contract->end_date->copy()->subDays($t),
                                'status' => 'skipped', 'generated_at' => now(), 'error' => 'Superseded by a later reminder (record captured after this date).',
                            ]);
                        }
                    }
                    $count += $this->dispatch($contract, "reminder_$due", $contract->end_date->copy()->subDays($due), "{$due}-day reminder", $managers, $channels);
                }
            } elseif (! in_array('expiry', $sentKeys, true)) {
                $count += $this->dispatch($contract, 'expiry', $contract->end_date, 'Expiry notification', $managers, $channels);
            } elseif ($days < 0 && ! $contract->renewal_decision) {
                $last = $contract->reminders->where('status', '!=', 'skipped')->max('generated_at');
                if (! $last || $last->copy()->startOfDay()->diffInDays(today()) >= $followUpEvery) {
                    $count += $this->dispatch($contract, 'follow_up_'.today()->format('Ymd'), today(), 'Follow-up', $managers, $channels);
                }
            }
        }

        return $count;
    }

    private function dispatch(Contract $contract, string $key, $scheduledFor, string $label, $managers, array $channels): int
    {
        $reminder = $contract->reminders()->create([
            'reminder_key' => $key,
            'scheduled_for' => $scheduledFor,
            'generated_at' => now(),
            'status' => 'generated',
            'channels' => implode(',', $channels),
            'recipients' => $managers->count(),
        ]);

        try {
            if ($managers->isEmpty()) {
                throw new \RuntimeException('No active Manager accounts to notify.');
            }
            Notification::send($managers, new ContractExpiryNotification($contract, $label, $channels));
            $reminder->update(['status' => 'sent']);
        } catch (Throwable $e) {
            $reminder->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        }

        AuditLog::record('contract.reminder', $contract, "{$label} for {$contract->reference}: {$reminder->status}");

        return 1;
    }
}
