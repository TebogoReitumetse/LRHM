<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRecord;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\LeaveApplication;
use App\Models\LeaveDocument;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only('user', 'event', 'subject', 'from', 'to', 'search');
        $user = $request->user();

        $logs = AuditLog::with('user')
            // Contract activity is confidential to the Manager in Phase 2.
            ->when(! $user->isManager(), fn ($q) => $q->where(fn ($q) => $q->whereNull('auditable_type')
                ->orWhereNotIn('auditable_type', [Contract::class, ContractDocument::class]))
                ->where('event', 'not like', 'contract.%'))
            // The system administrator sees system, account and settings activity, not HR records.
            ->when($user->isAdmin(), fn ($q) => $q->where(fn ($q) => $q->whereNull('auditable_type')
                ->orWhereNotIn('auditable_type', [LeaveApplication::class, LeaveDocument::class, AbsenceRecord::class]))
                ->where('event', 'not like', 'leave.%'))
            ->when($f['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['event'] ?? null, fn ($q, $v) => $q->where('event', 'like', $v.'%'))
            ->when($f['subject'] ?? null, fn ($q, $v) => $q->where('auditable_type', 'like', '%'.$v))
            ->when($f['search'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%$v%"))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')->latest('id')->paginate(40)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'filters' => $f,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
