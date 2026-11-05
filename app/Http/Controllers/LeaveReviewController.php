<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Services\LeaveWorkflow;
use Illuminate\Http\Request;

class LeaveReviewController extends Controller
{
    public function __construct(private LeaveWorkflow $workflow) {}

    /** The current user's action queue. */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isManagement(), 403);

        $statuses = $user->isDirector()
            ? [LeaveStatus::PendingDirectorApproval]
            : [LeaveStatus::Submitted, LeaveStatus::UnderManagerReview];

        $applications = LeaveApplication::with('category', 'user.department')
            ->status(...$statuses)->where('user_id', '!=', $user->id)
            ->oldest('submitted_at')->paginate(20);

        $returned = $user->isManager()
            ? LeaveApplication::with('category')->status(LeaveStatus::ReturnedForInformation)->latest('returned_at')->take(10)->get()
            : collect();

        return view('approvals.index', compact('applications', 'returned'));
    }

    public function startReview(Request $request, LeaveApplication $leave)
    {
        $this->authorize('review', $leave);
        $this->workflow->startReview($leave, $request->user());

        return back()->with('success', 'Review started.');
    }

    public function return(Request $request, LeaveApplication $leave)
    {
        $this->authorize('review', $leave);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $this->workflow->returnForInformation($leave, $request->user(), $data['comment']);

        return redirect()->route('approvals.index')->with('success', "{$leave->reference} returned to the employee for more information.");
    }

    public function forward(Request $request, LeaveApplication $leave)
    {
        $this->authorize('review', $leave);
        $data = $request->validate([
            'recommendation' => ['required', 'in:approve,decline'],
            'comment' => ['nullable', 'required_if:recommendation,decline', 'string', 'max:2000'],
        ], ['comment.required_if' => 'Please explain why approval is not recommended.']);
        $this->workflow->forward($leave, $request->user(), $data['recommendation'], $data['comment'] ?? null);

        return redirect()->route('approvals.index')->with('success', "{$leave->reference} forwarded to the Director.");
    }

    public function decide(Request $request, LeaveApplication $leave)
    {
        $this->authorize('decide', $leave);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'comment' => ['nullable', 'required_if:decision,decline', 'string', 'max:2000'],
        ], ['comment.required_if' => 'Please give a reason for declining.']);
        $approve = $data['decision'] === 'approve';
        $this->workflow->decide($leave, $request->user(), $approve, $data['comment'] ?? null);

        return redirect()->route('approvals.index')->with('success', "{$leave->reference} ".($approve ? 'approved' : 'declined').'. The employee has been notified.');
    }
}
