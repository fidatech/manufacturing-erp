<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\HrRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Daily attendance: one sheet per day with every employee on it. */
class AttendanceController extends Controller
{
    public static function statuses(): array
    {
        return ['present' => __('Present'), 'absent' => __('Absent'), 'leave' => __('On leave'), 'half' => __('Half day')];
    }

    public function index(Request $request): View
    {
        Gate::authorize('hr.view');

        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? today_iso();
        $monthStart = substr($date, 0, 8).'01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $summary = HrRecord::where('type', 'attendance')->whereDate('date', '>=', $monthStart)->whereDate('date', '<=', $monthEnd)
            ->groupBy('employee_id', 'status')->selectRaw('employee_id, status, COUNT(*) as days')->toBase()->get()->groupBy('employee_id')
            ->map(fn ($rows) => $rows->pluck('days', 'status'));

        return view('hr.attendance', [
            'date' => $date,
            'employees' => Account::ofType('employee')->active()->orderBy('name')->get(),
            'marked' => HrRecord::where('type', 'attendance')->whereDate('date', $date)->get()->keyBy('employee_id'),
            'summary' => $summary,
            'statuses' => self::statuses(),
            'month' => substr($date, 0, 7),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array'],
            'rows.*.status' => ['nullable', 'in:present,absent,leave,half'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $employees = Account::ofType('employee')->whereIn('id', array_keys($data['rows']))->pluck('id');
            foreach ($employees as $employeeId) {
                $row = $data['rows'][$employeeId];
                $existing = HrRecord::where('type', 'attendance')->where('employee_id', $employeeId)->whereDate('date', $data['date'])->first();
                if (empty($row['status'])) {
                    $existing?->delete();

                    continue;
                }
                $values = ['status' => $row['status'], 'notes' => $row['notes'] ?? null];
                $existing
                    ? $existing->update($values)
                    : HrRecord::create($values + ['type' => 'attendance', 'employee_id' => $employeeId, 'date' => $data['date'], 'created_by' => $request->user()->id]);
            }
        });

        return redirect()->route('attendance.index', ['date' => $data['date']])->with('success', __('Attendance saved.'));
    }
}
