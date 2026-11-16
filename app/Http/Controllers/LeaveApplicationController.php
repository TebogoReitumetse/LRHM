<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveCategory;
use App\Models\LeaveDocument;
use App\Models\User;
use App\Services\LeaveBalance;
use App\Services\LeaveBalanceService;
use App\Services\LeaveWorkflow;
use App\Services\WorkingDayCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaveApplicationController extends Controller
{
    /** Signatures are drawn on the form and posted as PNG data URLs. */
    public const SIGNATURE_PATTERN = '/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/';

    public const MAX_SIGNATURE_LENGTH = 600000;

    public function __construct(
        private WorkingDayCalculator $calendar,
        private LeaveWorkflow $workflow,
        private LeaveBalanceService $balances,
    ) {}

    /** The signed-in user's own applications. */
    public function index(Request $request)
    {
        $applications = $request->user()->leaveApplications()->with('category')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();

        return view('leave.index', compact('applications'));
    }

    public function create(Request $request)
    {
        return view('leave.form', [
            'application' => new LeaveApplication(['working_days' => 0]),
            'categories' => LeaveCategory::active()->get(),
            'user' => $request->user()->load('department'),
            'balanceMap' => $this->balanceMap($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request);

        $app = DB::transaction(function () use ($request, $user, $data) {
            $app = new LeaveApplication($data);
            $app->fill([
                'user_id' => $user->id,
                'employee_name' => $user->name,
                'employee_number' => $user->employee_number,
                'department' => $user->department?->name,
                'job_title' => $user->job_title,
                'status' => LeaveStatus::Draft,
            ]);
            $app->save();
            $this->rememberIdNumber($user, $data['id_number']);
            $user->rememberSignature($data['employee_signature']);
            $app->logAction('created', null, LeaveStatus::Draft);
            $this->storeDocuments($request, $app);

            return $app;
        });

        return $this->afterSave($request, $app);
    }

    public function show(LeaveApplication $leave)
    {
        $this->authorize('view', $leave);
        $leave->load('category', 'user.department', 'documents.uploader', 'actions.user', 'manager', 'director');

        return view('leave.show', [
            'application' => $leave,
            'holidays' => $this->calendar->holidaysBetween($leave->start_date, $leave->end_date),
            // The applicant's balance for this category, not counting this application.
            'balance' => $this->balances->forUser($leave->user, $leave->id)->get($leave->leave_category_id),
        ]);
    }

    public function edit(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);

        return view('leave.form', [
            'application' => $leave->load('documents'),
            'categories' => LeaveCategory::active()->get(),
            'user' => $request->user()->load('department'),
            'balanceMap' => $this->balanceMap($request->user(), $leave->id),
        ]);
    }

    public function update(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);
        $data = $this->validated($request, $leave);

        DB::transaction(function () use ($request, $leave, $data) {
            $leave->update($data);
            $this->rememberIdNumber($request->user(), $data['id_number']);
            $request->user()->rememberSignature($data['employee_signature']);
            $this->storeDocuments($request, $leave);
        });

        return $this->afterSave($request, $leave);
    }

    public function destroy(LeaveApplication $leave)
    {
        $this->authorize('delete', $leave);
        foreach ($leave->documents as $doc) {
            Storage::disk('local')->delete($doc->path);
        }
        $leave->delete();

        return redirect()->route('leave.index')->with('success', 'Draft deleted.');
    }

    public function submit(Request $request, LeaveApplication $leave)
    {
        $this->authorize('update', $leave);
        $this->assertSubmittable($leave);
        $this->workflow->submit($leave);

        return redirect()->route('leave.show', $leave)->with('success', "Application {$leave->reference} submitted for review.");
    }

    public function print(LeaveApplication $leave)
    {
        $this->authorize('view', $leave);
        $leave->load('category', 'user.department', 'documents', 'actions.user', 'manager', 'director');

        return view('leave.print', [
            'application' => $leave,
            // All tick boxes from the paper form, plus the chosen type if it has since been retired.
            'categories' => LeaveCategory::where('is_active', true)->orWhere('id', $leave->leave_category_id)
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function document(LeaveApplication $leave, LeaveDocument $document)
    {
        $this->authorize('view', $leave);
        abort_unless($document->leave_application_id === $leave->id, 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroyDocument(LeaveApplication $leave, LeaveDocument $document)
    {
        $this->authorize('update', $leave);
        abort_unless($document->leave_application_id === $leave->id, 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /** AJAX: working-day calculation for the form. */
    public function calculate(Request $request)
    {
        $request->validate(['start' => 'required|date', 'end' => 'required|date']);
        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        if ($end->lt($start)) {
            return response()->json(['days' => 0, 'error' => 'End date is before the start date.']);
        }

        return response()->json([
            'days' => $this->calendar->count($start, $end),
            'return_date' => $this->calendar->nextWorkingDay($end)->toDateString(),
            'holidays' => collect($this->calendar->holidaysBetween($start, $end))
                ->map(fn ($n, $d) => Carbon::parse($d)->format('D d M').' – '.$n)->values(),
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?LeaveApplication $existing = null): array
    {
        $category = LeaveCategory::find($request->input('leave_category_id'));
        $max = config('hrms.max_upload_kb');

        $data = $request->validate([
            'leave_category_id' => ['required', Rule::exists('leave_categories', 'id')->where('is_active', true)],
            'other_leave_type' => [$category?->requiresSpecification() ? 'required' : 'nullable', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'return_date' => ['required', 'date', 'after:end_date'],
            'id_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 ]+$/', Rule::unique('users')->ignore($request->user())],
            'employee_signature' => ['nullable', 'string', 'max:'.self::MAX_SIGNATURE_LENGTH, 'regex:'.self::SIGNATURE_PATTERN],
            'employee_signed_on' => ['nullable', 'date'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:'.config('hrms.upload_mimes'), "max:$max"],
        ], [
            'id_number.regex' => 'The ID number may only contain letters and numbers.',
            'id_number.unique' => 'This ID number is already on another employee\'s profile.',
            'other_leave_type.required' => 'Please specify the type of leave.',
            'return_date.after' => 'The date returning to work must be after the last day of leave.',
            'employee_signature.regex' => 'The signature could not be read. Please clear it and sign again.',
            'employee_signature.max' => 'The signature is too large. Please clear it and sign again.',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $days = $this->calendar->count($start, $end);

        if ($days === 0) {
            throw ValidationException::withMessages(['end_date' => 'The selected period has no working days (weekends and public holidays are excluded).']);
        }

        // Prevent overlapping applications for the same employee.
        $overlap = LeaveApplication::where('user_id', $request->user()->id)
            ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
            ->whereNotIn('status', [LeaveStatus::Declined->value, LeaveStatus::Draft->value])
            ->overlapping($start, $end)->first();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => "These dates overlap your application {$overlap->reference} ({$overlap->status->label()})."]);
        }

        // Signing the form is the employee declaration, as on the paper form.
        $signature = $data['employee_signature'] ?? null;
        $signedBefore = $existing?->declaration_accepted && $existing->employee_signature === $signature;

        return [
            // Blank keeps the ID already on the profile.
            'id_number' => $data['id_number'] ?? $request->user()->id_number,
            'leave_category_id' => $data['leave_category_id'],
            'other_leave_type' => $category?->requiresSpecification() ? $data['other_leave_type'] : null,
            'start_date' => $start,
            'end_date' => $end,
            'return_date' => $data['return_date'],
            'working_days' => $days,
            // Days beyond the balance for this type are counted as Unpaid Leave.
            'unpaid_days' => $this->balances->unpaidDaysFor($request->user(), $category, $days, $existing?->id),
            'employee_signature' => $signature,
            'employee_signed_on' => $signature ? ($data['employee_signed_on'] ?? today()) : null,
            'declaration_accepted' => (bool) $signature,
            'declaration_accepted_at' => $signature ? ($signedBefore ? $existing->declaration_accepted_at : now()) : null,
        ];
    }

    /** Keeps the ID number on the employee profile so the next application is pre-filled. */
    private function rememberIdNumber(User $user, ?string $idNumber): void
    {
        if ($idNumber && $user->id_number !== $idNumber) {
            $user->update(['id_number' => $idNumber]);
        }
    }

    private function storeDocuments(Request $request, LeaveApplication $app): void
    {
        foreach ($request->file('documents', []) as $file) {
            $app->documents()->create([
                'uploaded_by' => $request->user()->id,
                'path' => $file->store('leave/'.$app->id, 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function assertSubmittable(LeaveApplication $app): void
    {
        $app->load('category', 'documents');
        $errors = [];
        if (! $app->declaration_accepted || ! $app->employee_signature) {
            $errors['employee_signature'] = 'Please sign the employee declaration before submitting.';
        }
        if ($app->category->requiresDocumentFor((float) $app->working_days) && $app->documents->isEmpty()) {
            $errors['documents'] = $app->category->document_after_days
                ? "Please attach a medical certificate. It is required for {$app->category->name} of more than {$app->category->document_after_days} days."
                : "A supporting document is required for {$app->category->name}.";
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function afterSave(Request $request, LeaveApplication $app)
    {
        if ($request->input('action') === 'submit') {
            try {
                $this->assertSubmittable($app);
            } catch (ValidationException $e) {
                return redirect()->route('leave.edit', $app)->withErrors($e->errors())
                    ->with('warning', 'Your application was saved as a draft but could not be submitted yet.');
            }
            $this->workflow->submit($app);

            return redirect()->route('leave.show', $app)->with('success', "Application {$app->reference} submitted. Your Manager has been notified.");
        }

        return redirect()->route('leave.show', $app)->with('success', 'Application saved as a draft.');
    }

    /** Tracked balances keyed by category id, for the live check on the application form. */
    private function balanceMap($user, ?int $excludeApplicationId = null): array
    {
        $balances = $this->balances->forUser($user, $excludeApplicationId);

        $map = $balances
            ->filter(fn (LeaveBalance $b) => $b->tracked())
            ->map(fn (LeaveBalance $b) => [
                'tracked' => true,
                'entitlement' => (float) LeaveBalance::days($b->entitlement),
                'taken' => (float) LeaveBalance::days($b->taken),
                'pending' => (float) LeaveBalance::days($b->pending),
                'available' => (float) LeaveBalance::days($b->availableAfterPending()),
                'cycle' => $b->cycleLabel(),
            ])->all();

        // Types without a day balance (e.g. maternity) still show their entitlement and this year's leave.
        $year = now()->startOfYear()->format('d M Y').' – '.now()->endOfYear()->format('d M Y');
        foreach (LeaveCategory::whereIn('name', array_keys(LeaveCategory::UNTRACKED_ENTITLEMENTS))->get() as $category) {
            $balance = $balances->get($category->id);
            $map[$category->id] ??= [
                'tracked' => false,
                'entitlements' => LeaveCategory::UNTRACKED_ENTITLEMENTS[$category->name],
                'taken' => (float) LeaveBalance::days($balance?->taken ?? 0),
                'pending' => (float) LeaveBalance::days($balance?->pending ?? 0),
                'cycle' => $balance?->cycleLabel() ?? $year,
            ];
        }

        return $map;
    }
}
