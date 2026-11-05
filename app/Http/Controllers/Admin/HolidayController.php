<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PublicHoliday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        return view('admin.holidays', [
            'holidays' => PublicHoliday::whereYear('date', $year)->orderBy('date')->get(),
            'year' => $year,
            'years' => PublicHoliday::selectRaw('YEAR(date) as y')->distinct()->orderBy('y')->pluck('y')->push(now()->year)->push(now()->year + 1)->unique()->sort()->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:public_holidays,date'],
            'name' => ['required', 'string', 'max:100'],
        ], ['date.unique' => 'A public holiday is already captured for that date.']);
        $h = PublicHoliday::create($data);
        AuditLog::record('settings.holiday', $h, "Public holiday {$h->name} ({$h->date->format('d M Y')}) added");

        return redirect()->route('admin.holidays.index', ['year' => $h->date->year])->with('success', 'Public holiday added.');
    }

    public function destroy(PublicHoliday $holiday)
    {
        AuditLog::record('settings.holiday', $holiday, "Public holiday {$holiday->name} ({$holiday->date->format('d M Y')}) removed");
        $holiday->delete();

        return back()->with('success', 'Public holiday removed.');
    }
}
