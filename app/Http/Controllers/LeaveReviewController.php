<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Services\LeaveWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'approver_signature' => ['required', 'string', 'max:'.LeaveApplicationController::MAX_SIGNATURE_LENGTH,
                'regex:'.LeaveApplicationController::SIGNATURE_PATTERN],
            'approver_name' => ['required', 'string', 'max:150'],
        ], [
            'comment.required_if' => 'Please explain why approval is not recommended.',
            'approver_signature.required' => 'Please sign the form as Manager/Owner.',
            'approver_signature.regex' => 'The signature could not be read. Please clear it and sign again.',
            'approver_name.required' => 'Please print your name.',
        ]);
        $this->workflow->forward($leave, $request->user(), $data['recommendation'], $data['comment'] ?? null,
            $data['approver_signature'], $data['approver_name']);
        $request->user()->rememberSignature($data['approver_signature']);

        return redirect()->route('approvals.index')->with('success', "{$leave->reference} forwarded to the Director.");
    }

    public function decide(Request $request, LeaveApplication $leave)
    {
        $this->authorize('decide', $leave);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'comment' => ['nullable', 'required_if:decision,decline', 'string', 'max:2000'],
            // The Director must sign to approve. A decline only needs a signature when no Manager signed
            // the Manager/Owner Signature while forwarding (e.g. routed straight to the Director).
            'director_signature' => [Rule::requiredIf($request->input('decision') === 'approve' || ! $leave->approver_signature),
                'nullable', 'string', 'max:'.LeaveApplicationController::MAX_SIGNATURE_LENGTH,
                'regex:'.LeaveApplicationController::SIGNATURE_PATTERN],
            'approver_name' => ['nullable', 'string', 'max:150'],
        ], [
            'comment.required_if' => 'Please give the reason for declining.',
            'director_signature.required' => 'Please sign as Director before making a decision.',
            'director_signature.regex' => 'The signature could not be read. Please clear it and sign again.',
        ]);
        $approve = $data['decision'] === 'approve';
        $this->workflow->decide($leave, $request->user(), $approve, $data['comment'] ?? null,
            $data['director_signature'] ?? null, $data['approver_name'] ?? null);
        $request->user()->rememberSignature($data['director_signature'] ?? null);

        return redirect()->route('approvals.index')->with('success', "{$leave->reference} ".($approve ? 'approved' : 'declined').'. The employee has been notified.');
    }
}
