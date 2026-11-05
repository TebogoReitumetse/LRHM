<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('admin.departments', ['departments' => Department::withCount('users')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Department::create($request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:departments'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Department added.');
    }

    public function update(Request $request, Department $department)
    {
        $department->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments')->ignore($department)],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        if ($department->users()->exists()) {
            return back()->with('error', 'This department still has staff assigned to it.');
        }
        $department->delete();

        return back()->with('success', 'Department removed.');
    }
}
