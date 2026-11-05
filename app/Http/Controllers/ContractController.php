<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContractService;
use App\Support\Export;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    private const FILTERS = ['search', 'department', 'type', 'status', 'expiry', 'expiry_from', 'expiry_to'];

    public function __construct(private ContractService $service) {}

    public function dashboard()
    {
        $this->service->refreshStatuses();
        $active = [ContractStatus::Active->value, ContractStatus::DueToExpire->value];

        return view('contracts.dashboard', [
            'stats' => [
                'active' => Contract::whereIn('status', $active)->count(),
                'within90' => Contract::expiringWithin(90)->count(),
                'within60' => Contract::expiringWithin(60)->count(),
                'within30' => Contract::expiringWithin(30)->count(),
                'this_month' => Contract::monitored()->whereBetween('end_date', [today()->startOfMonth(), today()->endOfMonth()])->count(),
                'expired' => Contract::where('status', ContractStatus::Expired->value)->count(),
                'awaiting' => Contract::awaitingDecision()->count(),
                'renewed' => Contract::whereIn('status', [ContractStatus::Renewed->value, ContractStatus::Superseded->value])->count(),
            ],
            'expiring' => Contract::with('department')->expiringWithin(90)->orderBy('end_date')->get(),
            'expired' => Contract::with('department')->where('status', ContractStatus::Expired->value)->orderBy('end_date')->get(),
            'recentReminders' => \App\Models\ContractReminder::with('contract')->latest('generated_at')->take(8)->get(),
            'reminderDays' => Setting::reminderDays(),
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->only(self::FILTERS);

        return view('contracts.index', [
            'contracts' => Contract::with('department')->filter($filters)
                ->when(! ($filters['status'] ?? null) && ! $request->boolean('history'),
                    fn ($q) => $q->whereNotIn('status', [ContractStatus::Renewed->value, ContractStatus::Superseded->value]))
                ->orderBy('end_date')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, string $format)
    {
        $contracts = Contract::with('department')->filter($request->only(self::FILTERS))->orderBy('end_date')->get();
        $name = 'contract-register-'.now()->format('Ymd-His');

        if ($format === 'csv') {
            return Export::csv($name,
                ['Reference', 'Full name', 'Employee no.', 'Job title', 'Department', 'Contract type', 'Start date', 'End date',
                    'Duration', 'Date signed', 'Commencement', 'Status', 'Days remaining', 'Renewal decision', 'Decided by', 'Decision date'],
                $contracts->map(fn (Contract $c) => [
                    $c->reference, $c->full_name, $c->employee_number, $c->job_title, $c->department?->name, $c->typeLabel(),
                    $c->start_date->format('Y-m-d'), $c->end_date->format('Y-m-d'), $c->durationLabel(),
                    $c->date_signed?->format('Y-m-d'), $c->commencement_date?->format('Y-m-d'), $c->status->label(),
                    in_array($c->status, ContractStatus::monitored(), true) ? $c->daysRemaining() : '',
                    match ($c->renewal_decision) { 'renew' => 'Renewed', 'not_renew' => 'Not renewed', default => '' },
                    $c->renewal_decided_by_name, $c->renewal_decision_date?->format('Y-m-d'),
                ]));
        }

        $data = ['title' => 'Contract Register', 'subtitle' => 'Generated '.now()->format('d M Y H:i'), 'contracts' => $contracts];

        return $format === 'pdf' ? Export::pdf('contracts.document', $data, $name) : Export::print('contracts.document', $data);
    }

    public function create(Request $request)
    {
        $user = $request->integer('user') ? User::with('department')->find($request->integer('user')) : null;

        return view('contracts.form', [
            'contract' => new Contract([
                'user_id' => $user?->id, 'full_name' => $user?->name, 'employee_number' => $user?->employee_number,
                'job_title' => $user?->job_title, 'department_id' => $user?->department_id,
                'contract_type' => $user?->employment_type === 'contractor' ? 'contractor' : 'fixed_term',
                'status' => ContractStatus::Draft,
            ]),
            'people' => User::orderBy('name')->get(['id', 'name', 'employee_number', 'job_title', 'department_id', 'employment_type']),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $contract = DB::transaction(function () use ($request, $data) {
            $contract = Contract::create($data + ['reference' => $this->service->nextReference(), 'created_by' => $request->user()->id]);
            $this->applyMonitoredStatus($contract);
            $this->uploads($request, $contract);

            return $contract;
        });

        return redirect()->route('contracts.show', $contract)->with('success', "Contract {$contract->reference} created.");
    }

    public function show(Contract $contract)
    {
        $this->authorize('view', $contract);
        $contract->load('department', 'documents.uploader', 'creator', 'renewalRecorder', 'user');

        $history = collect();
        for ($c = $contract->previous; $c; $c = $c->previous) {
            $history->push($c);
        }
        $newer = collect();
        for ($c = $contract->next; $c; $c = $c->next) {
            $newer->push($c);
        }

        $isManager = request()->user()->isManager();

        return view('contracts.show', [
            'contract' => $contract,
            'history' => $history,
            'newer' => $newer,
            'reminders' => $isManager ? $contract->reminders()->get() : collect(),
            'audit' => $isManager ? AuditLog::with('user')->where('auditable_type', Contract::class)->where('auditable_id', $contract->id)->latest('created_at')->get() : collect(),
        ]);
    }

    /** Employee / contractor self-service: their own contracts. */
    public function mine(Request $request)
    {
        $user = $request->user();
        $contracts = $user->can_view_contract
            ? $user->contracts()->with('documents')->where('status', '!=', ContractStatus::Draft->value)->orderByDesc('start_date')->get()
            : collect();

        return view('contracts.mine', ['contracts' => $contracts, 'enabled' => $user->can_view_contract]);
    }

    public function edit(Contract $contract)
    {
        abort_if(in_array($contract->status, [ContractStatus::Renewed, ContractStatus::Superseded, ContractStatus::Ended], true), 403,
            'Closed contract versions are kept unchanged for the history.');

        return view('contracts.form', [
            'contract' => $contract,
            'people' => User::orderBy('name')->get(['id', 'name', 'employee_number', 'job_title', 'department_id', 'employment_type']),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Contract $contract)
    {
        abort_if(in_array($contract->status, [ContractStatus::Renewed, ContractStatus::Superseded, ContractStatus::Ended], true), 403);
        $data = $this->validated($request, $contract);

        DB::transaction(function () use ($request, $contract, $data) {
            $contract->update($data);
            $this->applyMonitoredStatus($contract);
            $this->uploads($request, $contract);
        });

        return redirect()->route('contracts.show', $contract)->with('success', 'Contract updated.');
    }

    public function upload(Request $request, Contract $contract)
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:'.config('hrms.upload_mimes'), 'max:'.config('hrms.max_upload_kb')],
            'document_type' => ['required', Rule::in(array_keys(ContractDocument::TYPES))],
        ]);
        $this->service->storeDocument($contract, $request->file('document'), $request->document_type, $request->user());

        return back()->with('success', 'Document uploaded.');
    }

    public function document(Contract $contract, ContractDocument $document)
    {
        $this->authorize('view', $contract);
        abort_unless($document->contract_id === $contract->id, 404);
        AuditLog::record('contract.document_viewed', $contract, 'Downloaded "'.$document->original_name.'"');

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function renewForm(Contract $contract)
    {
        abort_unless(in_array($contract->status, ContractStatus::monitored(), true), 403, 'Only active, expiring or expired contracts can be renewed.');

        return view('contracts.renew', ['contract' => $contract]);
    }

    public function renew(Request $request, Contract $contract)
    {
        abort_unless(in_array($contract->status, ContractStatus::monitored(), true), 403);
        $data = $request->validate([
            'renewal_kind' => ['required', 'in:renewal,extension'],
            'contract_type' => ['required', Rule::in(array_keys(Contract::TYPES))],
            'job_title' => ['nullable', 'string', 'max:150'],
            'start_date' => ['required', 'date', 'after:'.$contract->start_date->toDateString()],
            'end_date' => ['required', 'date', 'after:start_date'],
            'date_signed' => ['nullable', 'date'],
            'commencement_date' => ['nullable', 'date'],
            'decided_by_name' => ['required', 'string', 'max:150'],
            'decision_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:'.config('hrms.upload_mimes'), 'max:'.config('hrms.max_upload_kb')],
            'dates_verified' => ['accepted'],
        ], ['dates_verified.accepted' => 'Confirm that the new dates were checked against the signed renewal or addendum.']);

        $new = $this->service->renew($contract, $data, $request->file('document'), $request->user());

        return redirect()->route('contracts.show', $new)->with('success', "Recorded. New contract version {$new->reference} created; {$contract->reference} kept in the history.");
    }

    public function end(Request $request, Contract $contract)
    {
        abort_unless(in_array($contract->status, ContractStatus::monitored(), true), 403);
        $data = $request->validate([
            'decided_by_name' => ['required', 'string', 'max:150'],
            'decision_date' => ['required', 'date', 'before_or_equal:today'],
            'ended_on' => ['required', 'date'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $this->service->end($contract, $data, $request->user());

        return back()->with('success', 'Non-renewal decision recorded and contract marked as ended.');
    }

    public function runMonitor()
    {
        $changed = $this->service->refreshStatuses();
        $sent = $this->service->sendReminders();

        return back()->with('success', "Expiry check complete. Statuses updated: {$changed}. Reminders generated: {$sent}.");
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?Contract $existing = null): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:150'],
            'employee_number' => ['required', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'contract_type' => ['required', Rule::in(array_keys(Contract::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'date_signed' => ['nullable', 'date'],
            'commencement_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys(ContractStatus::manualOptions()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'dates_verified' => ['nullable', 'boolean'],
            'signed_contract' => ['nullable', 'file', 'mimes:'.config('hrms.upload_mimes'), 'max:'.config('hrms.max_upload_kb')],
            'supporting' => ['nullable', 'array', 'max:5'],
            'supporting.*' => ['file', 'mimes:'.config('hrms.upload_mimes'), 'max:'.config('hrms.max_upload_kb')],
        ]);

        if ($data['status'] === ContractStatus::Active->value && ! $request->boolean('dates_verified')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dates_verified' => 'Confirm that the dates were verified against the signed contract before activating it.',
            ]);
        }

        $data['dates_verified'] = $request->boolean('dates_verified');
        unset($data['signed_contract'], $data['supporting']);

        return $data;
    }

    /** Active contracts get Active / Due to Expire / Expired from their dates. */
    private function applyMonitoredStatus(Contract $contract): void
    {
        if (in_array($contract->status, ContractStatus::monitored(), true)) {
            $status = $this->service->monitoredStatusFor($contract);
            if ($status !== $contract->status) {
                $contract->update(['status' => $status]);
            }
        }
    }

    private function uploads(Request $request, Contract $contract): void
    {
        if ($file = $request->file('signed_contract')) {
            $this->service->storeDocument($contract, $file, 'signed_contract', $request->user());
        }
        foreach ($request->file('supporting', []) as $file) {
            $this->service->storeDocument($contract, $file, 'other', $request->user());
        }
    }
}
