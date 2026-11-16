<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\Role;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Notifications\LeaveApplicationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Employee -> Manager review -> Director decision.
 * The Manager reviews and forwards; only the Director makes the final decision.
 */
class LeaveWorkflow
{
    public function __construct(private LeaveBalanceService $balances) {}

    public function submit(LeaveApplication $app): void
    {
        $this->guard($app, [LeaveStatus::Draft, LeaveStatus::ReturnedForInformation]);
        $from = $app->status;
        $resubmission = $from === LeaveStatus::ReturnedForInformation;

        DB::transaction(function () use ($app, $from, $resubmission) {
            $app->reference ??= $this->nextReference();
            $app->submitted_at ??= now();
            // The balance may have changed since the draft was saved.
            $app->unpaid_days = $this->balances->unpaidDaysFor($app->user, $app->category, (float) $app->working_days, $app->id);

            $reviewers = $this->managersFor($app);
            if ($reviewers->isEmpty()) {
                // No independent manager (e.g. the applicant is the only Manager): go straight to the Director.
                $app->status = LeaveStatus::PendingDirectorApproval;
                $app->save();
                $app->logAction($resubmission ? 'resubmitted' : 'submitted', $from, LeaveStatus::PendingDirectorApproval,
                    'No independent Manager available; routed directly to the Director.');
                $this->notify($this->directorsFor($app), $app, 'forwarded');

                return;
            }

            $app->status = LeaveStatus::Submitted;
            $app->save();
            $app->logAction($resubmission ? 'resubmitted' : 'submitted', $from, LeaveStatus::Submitted);
            $this->notify($reviewers, $app, $resubmission ? 'resubmitted' : 'submitted');
            if (! $resubmission) {
                // Directors are told about every new application; they decide once the Manager forwards it.
                $this->notify($this->directorsFor($app), $app, 'submitted_for_director');
            }
        });
    }

    public function startReview(LeaveApplication $app, User $manager): void
    {
        $this->guard($app, [LeaveStatus::Submitted]);
        $app->update(['status' => LeaveStatus::UnderManagerReview, 'manager_id' => $manager->id]);
        $app->logAction('review_started', LeaveStatus::Submitted, LeaveStatus::UnderManagerReview);
    }

    public function returnForInformation(LeaveApplication $app, User $manager, string $comment): void
    {
        $this->guard($app, [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview]);
        $from = $app->status;
        $app->update([
            'status' => LeaveStatus::ReturnedForInformation,
            'manager_id' => $manager->id,
            'return_reason' => $comment,
            'returned_at' => now(),
            // The employee must sign the declaration again when resubmitting.
            'declaration_accepted' => false,
            'declaration_accepted_at' => null,
            'employee_signature' => null,
            'employee_signed_on' => null,
        ]);
        $app->logAction('returned', $from, LeaveStatus::ReturnedForInformation, $comment);
        $this->notify(collect([$app->user]), $app, 'returned', $comment);
    }

    /**
     * The Manager signs the form's Manager/Owner Signature when forwarding it to the Director.
     */
    public function forward(LeaveApplication $app, User $manager, string $recommendation, ?string $comment,
        ?string $signature = null, ?string $printName = null): void
    {
        $this->guard($app, [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview]);
        $from = $app->status;
        $app->update([
            'status' => LeaveStatus::PendingDirectorApproval,
            'manager_id' => $manager->id,
            'manager_recommendation' => $recommendation,
            'manager_comment' => $comment,
            'manager_reviewed_at' => now(),
            'approver_signature' => $signature,
            'approver_name' => $signature ? ($printName ?: $manager->name) : null,
        ]);
        $label = $recommendation === 'approve' ? 'Recommends approval' : 'Does not recommend approval';
        $app->logAction('forwarded', $from, LeaveStatus::PendingDirectorApproval, trim($label.'. '.$comment));
        $this->notify($this->directorsFor($app), $app, 'forwarded', $comment);
    }

    /**
     * Office Use Only: Approved / Declined (reason). The Director signs the decision. The Manager/Owner
     * signature is the Manager's, captured when forwarding; when no Manager signed, the Director's
     * signature fills it as well.
     */
    public function decide(LeaveApplication $app, User $director, bool $approve, ?string $comment,
        ?string $signature = null, ?string $printName = null): void
    {
        $this->guard($app, [LeaveStatus::PendingDirectorApproval]);
        $to = $approve ? LeaveStatus::Approved : LeaveStatus::Declined;
        $attributes = [
            'status' => $to,
            'director_id' => $director->id,
            'director_comment' => $comment,
            'decided_at' => now(),
            'director_signature' => $signature,
        ];
        if (! $app->approver_signature && $signature) {
            $attributes['approver_signature'] = $signature;
            $attributes['approver_name'] = $printName ?: $director->name;
        }
        $app->update($attributes);
        $app->logAction($approve ? 'approved' : 'declined', LeaveStatus::PendingDirectorApproval, $to, $comment);
        $this->notify(collect([$app->user]), $app, $approve ? 'approved' : 'declined', $comment);
        if ($app->manager && $app->manager->isNot($app->user)) {
            $this->notify(collect([$app->manager]), $app, 'decided', $comment);
        }
    }

    /** Active managers other than the applicant. */
    public function managersFor(LeaveApplication $app): Collection
    {
        return User::active()->withRole(Role::Manager)->whereKeyNot($app->user_id)->get();
    }

    public function directorsFor(LeaveApplication $app): Collection
    {
        return User::active()->withRole(Role::Director)->whereKeyNot($app->user_id)->get();
    }

    private function notify(Collection $users, LeaveApplication $app, string $event, ?string $comment = null): void
    {
        $users = $users->filter();
        if ($users->isNotEmpty()) {
            Notification::send($users, new LeaveApplicationNotification($app->fresh(['category', 'user']), $event, $comment));
        }
    }

    private function guard(LeaveApplication $app, array $allowed): void
    {
        if (! in_array($app->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "This action is not available while the application is '{$app->status->label()}'.",
            ]);
        }
    }

    private function nextReference(): string
    {
        $prefix = 'LV-'.now()->format('Y').'-';
        $last = LeaveApplication::where('reference', 'like', $prefix.'%')->lockForUpdate()->max('reference');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
