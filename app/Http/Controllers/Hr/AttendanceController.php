<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AttendanceMonth;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Attendance month by month: one sheet for a month with every employee on it,
 * and for each the days present, absent, on leave and holidays.
 */
class AttendanceController extends Controller
{
    /** The four numbers of the sheet, in the order of its columns. */
    public static function columns(): array
    {
        return ['present' => __('Present days'), 'absent' => __('Absent days'), 'leave' => __('Leave'), 'holidays' => __('Holidays')];
    }

    public function index(Request $request): View
    {
        Gate::authorize('hr.view');

        $period = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? substr(today_iso(), 0, 7);
        $start = Carbon::parse($period.'-01');

        $employees = Account::ofType('employee')->active()->orderBy('name')->get();
        $rows = AttendanceMonth::whereDate('month', $start->toDateString())->get()->keyBy('employee_id');

        $totals = array_fill_keys(AttendanceMonth::COLUMNS, '0');
        foreach ($rows as $row) {
            foreach (AttendanceMonth::COLUMNS as $column) {
                $totals[$column] = Num::add($totals[$column], $row->{$column});
            }
        }

        // the usual month, shown for an employee whose line is not saved yet:
        // every Friday is a holiday, every other day is a present day
        $fridays = 0;
        for ($day = $start->copy(); $day->month === $start->month; $day->addDay()) {
            $fridays += $day->isFriday() ? 1 : 0;
        }

        return view('hr.attendance', [
            'period' => $period,
            'daysInMonth' => $start->daysInMonth,
            'usual' => ['present' => (string) ($start->daysInMonth - $fridays), 'absent' => '0', 'leave' => '0', 'holidays' => (string) $fridays],
            'previous' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            'employees' => $employees,
            'rows' => $rows,
            'totals' => $totals,
            'columns' => self::columns(),
            // on paper and in Excel the sheet is shown as plain numbers, not as boxes to type in
            'editing' => $request->user()->can('hr.manage') && ! $request->boolean('print') && ! $request->filled('export'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'rows' => ['required', 'array'],
            'rows.*.present' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'rows.*.absent' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'rows.*.leave' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'rows.*.holidays' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'rows.*.notes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'rows.*.present' => __('present days'), 'rows.*.absent' => __('absent days'), 'rows.*.leave' => __('leave'), 'rows.*.holidays' => __('holidays'),
        ]);

        $start = Carbon::parse($data['month'].'-01');
        if ($start->isAfter(Carbon::parse(today_iso())->startOfMonth())) {
            throw new UserError(__('A month that has not started yet cannot be filled in.'));
        }
        $days = (string) $start->daysInMonth;

        DB::transaction(function () use ($data, $start, $days, $request) {
            $employees = Account::ofType('employee')->whereIn('id', array_keys($data['rows']))->get()->keyBy('id');
            foreach ($data['rows'] as $employeeId => $row) {
                $employee = $employees->get($employeeId);
                if (! $employee) {
                    continue;
                }
                $values = [];
                foreach (AttendanceMonth::COLUMNS as $column) {
                    $values[$column] = Num::round($row[$column] ?? 0, 1);
                }
                $sum = Num::sum(array_values($values));
                if (Num::cmp($sum, $days) > 0) {
                    throw new UserError(__(':name: the days add up to :sum, but :month has only :days days.', [
                        'name' => $employee->name, 'sum' => Num::format($sum, 1, true), 'month' => $data['month'], 'days' => $days,
                    ]));
                }

                $existing = AttendanceMonth::where('employee_id', $employee->id)->whereDate('month', $start->toDateString())->first();
                // a line with nothing in it is not kept
                if (Num::isZero($sum) && empty($row['notes'])) {
                    $existing?->delete();

                    continue;
                }
                $values['notes'] = $row['notes'] ?? null;
                $existing
                    ? $existing->update($values)
                    : AttendanceMonth::create($values + ['employee_id' => $employee->id, 'month' => $start->toDateString(), 'created_by' => $request->user()->id]);
            }
        });

        return redirect()->route('attendance.index', ['month' => $data['month']])->with('success', __('Attendance of :month saved.', ['month' => $data['month']]));
    }
}
