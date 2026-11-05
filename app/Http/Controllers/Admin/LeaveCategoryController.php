<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LeaveCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Leave categories must match LivityICT's approved HR policy.
 * Changes are recorded in the audit trail.
 */
class LeaveCategoryController extends Controller
{
    public function index()
    {
        return view('admin.leave-categories', ['categories' => LeaveCategory::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $c = LeaveCategory::create($this->validated($request));
        AuditLog::record('settings.leave_category', $c, "Leave category \"{$c->name}\" added", null, $c->toArray());

        return back()->with('success', 'Leave category added.');
    }

    public function update(Request $request, LeaveCategory $leaveCategory)
    {
        $old = $leaveCategory->toArray();
        $leaveCategory->update($this->validated($request, $leaveCategory));
        AuditLog::record('settings.leave_category', $leaveCategory, "Leave category \"{$leaveCategory->name}\" updated", $old, $leaveCategory->toArray());

        return back()->with('success', 'Leave category updated.');
    }

    private function validated(Request $request, ?LeaveCategory $c = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_categories')->ignore($c)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return $data + [
            'requires_document' => $request->boolean('requires_document'),
            'requires_reason' => $request->boolean('requires_reason'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
