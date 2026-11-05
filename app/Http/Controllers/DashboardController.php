<?php

namespace App\Http\Controllers;

use App\Enums\AbsenceClassification;
use App\Enums\ContractStatus;
use App\Enums\LeaveStatus;
use App\Models\AbsenceRecord;
use App\Models\Contract;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BackupService $backups)
    {
        $user = $request->user();
        $data = ['user' => $user];

        // Personal section for every user.
        $mine = $user->leaveApplications();
        $data['my'] = [
            'recent' => (clone $mine)->with('category')->latest('updated_at')->take(5)->get(),
            'pending' => (clone $mine)->status(LeaveStatus::Submitted, LeaveStatus::UnderManagerReview, LeaveStatus::PendingDirectorApproval)->count(),
            'returned' => (clone $mine)->status(LeaveStatus::ReturnedForInformation)->count(),
            'approved' => (clone $mine)->status(LeaveStatus::Approved)->whereYear('start_date', now()->year)->count(),
            'drafts' => (clone $mine)->status(LeaveStatus::Draft)->count(),
            'next' => (clone $mine)->status(LeaveStatus::Approved)->whereDate('end_date', '>=', today())->orderBy('start_date')->first(),
        ];
        $data['myContract'] = $user->can_view_contract
            ? $user->contracts()->whereNotIn('status', [ContractStatus::Draft->value])->latest('start_date')->first()
            : null;

        if ($user->isManagement()) {
            $apps = LeaveApplication::query()->submittedOnly();
            $today = today();
            $data['stats'] = [
                'total' => (clone $apps)->count(),
                'awaiting_manager' => (clone $apps)->status(LeaveStatus::Submitted, LeaveStatus::UnderManagerReview)->count(),
                'awaiting_director' => (clone $apps)->status(LeaveStatus::PendingDirectorApproval)->count(),
                'upcoming' => (clone $apps)->status(LeaveStatus::Approved)->whereDate('start_date', '>', $today)->count(),
                'on_leave' => (clone $apps)->status(LeaveStatus::Approved)->overlapping($today, $today)->count(),
                'declined' => (clone $apps)->status(LeaveStatus::Declined)->count(),
                'returned' => (clone $apps)->status(LeaveStatus::ReturnedForInformation)->count(),
            ];
            $data['onLeave'] = LeaveApplication::with('category', 'user.department')->status(LeaveStatus::Approved)
                ->overlapping($today, $today)->orderBy('return_date')->get();
            $data['upcoming'] = LeaveApplication::with('category')->status(LeaveStatus::Approved)
                ->whereDate('start_date', '>', $today)->whereDate('start_date', '<=', $today->copy()->addDays(30))
                ->orderBy('start_date')->take(8)->get();
            $data['actionQueue'] = LeaveApplication::with('category')
                ->status(...($user->isDirector()
                    ? [LeaveStatus::PendingDirectorApproval]
                    : [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview]))
                ->where('user_id', '!=', $user->id)->oldest('submitted_at')->take(8)->get();
            $data['absencesOpen'] = AbsenceRecord::where('follow_up_status', '!=', 'closed')->count();
            $data['absencesPending'] = AbsenceRecord::where('classification', AbsenceClassification::PendingExplanation->value)->count();
            $data['byMonth'] = $this->approvedByMonth();
        }

        if ($user->isManager()) {
            $data['contracts'] = [
                'active' => Contract::monitored()->where('status', '!=', ContractStatus::Expired->value)->count(),
                'within90' => Contract::expiringWithin(90)->count(),
                'within30' => Contract::expiringWithin(30)->count(),
                'expired' => Contract::where('status', ContractStatus::Expired->value)->count(),
                'awaiting' => Contract::awaitingDecision()->count(),
            ];
            $data['expiringSoon'] = Contract::expiringWithin(90)->orderBy('end_date')->take(5)->get();
        }

        if ($user->isAdmin()) {
            $data['admin'] = [
                'users' => User::active()->count(),
                'inactive' => User::where('is_active', false)->count(),
                'lastBackup' => $backups->list()[0] ?? null,
            ];
        }

        return view('dashboard', $data);
    }

    /** Approved working days per month for the last 6 months (by start date). */
    private function approvedByMonth(): array
    {
        $start = today()->startOfMonth()->subMonths(5);
        $rows = LeaveApplication::status(LeaveStatus::Approved)->whereDate('start_date', '>=', $start)
            ->get(['start_date', 'working_days'])
            ->groupBy(fn ($a) => $a->start_date->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('working_days'));

        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i);
            $out[] = ['label' => $m->format('M'), 'value' => $rows[$m->format('Y-m')] ?? 0];
        }

        return $out;
    }
}
