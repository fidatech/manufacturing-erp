<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Payroll;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Monthly salaries.
 * Saving a payroll adds every employee's pay to his or her account ("we owe").
 * Advances already taken are on the same account, so the balance shows what is
 * still to be paid. Paying is done with Money out.
 */
class PayrollController extends Controller
{
    public function index(): View
    {
        Gate::authorize('hr.view');

        return view('payroll.index', ['payrolls' => Payroll::withCount('lines')->orderByDesc('period')->paginate(per_page(24))]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('hr.manage');

        $period = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('period')) ? $request->query('period') : now()->format('Y-m');

        $start = $period.'-01';
        $end = date('Y-m-t', strtotime($start));
        $adjustments = \App\Models\HrRecord::whereIn('type', ['allowance', 'deduction'])
            ->whereDate('date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))
            ->groupBy('employee_id', 'type')->selectRaw('employee_id, type, SUM(amount) as total')->toBase()->get()
            ->groupBy('employee_id')->map(fn ($rows) => $rows->pluck('total', 'type'));

        return view('payroll.form', [
            'adjustments' => $adjustments,
            'period' => $period,
            'exists' => Payroll::where('period', $period)->exists(),
            'employees' => Account::ofType('employee')->active()->with('currency')->orderBy('name')->get(),
            'balances' => Ledger::balances(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m', 'unique:payrolls,period'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.employee_id' => ['required', 'exists:accounts,id'],
            'lines.*.include' => ['nullable'],
            'lines.*.salary' => ['nullable', 'numeric', 'min:0'],
            'lines.*.bonus' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deduction' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ], ['period.unique' => __('The salaries of this month were already saved.')]);

        $payroll = DB::transaction(function () use ($data) {
            $payroll = Payroll::create(['period' => $data['period'], 'date' => $data['date'], 'status' => 'posted', 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id()]);
            $baseId = Rates::base()->id;
            $entry = [];
            $expense = '0';
            $taxTotal = '0';

            foreach ($data['lines'] as $line) {
                if (empty($line['include'])) {
                    continue;
                }
                $employee = Account::ofType('employee')->findOrFail($line['employee_id']);
                $salary = Num::money($line['salary'] ?? 0);
                $bonus = Num::money($line['bonus'] ?? 0);
                $deduction = Num::money($line['deduction'] ?? 0);
                $tax = Num::money($line['tax'] ?? 0);
                $earned = Num::money(Num::sub(Num::add($salary, $bonus), $deduction));
                $net = Num::money(Num::sub($earned, $tax));
                if (Num::isNegative($net)) {
                    throw new UserError(__('The deductions of :name are bigger than the salary.', ['name' => $employee->name]));
                }
                if (Num::isZero($earned)) {
                    continue;
                }

                $payroll->lines()->create(['employee_id' => $employee->id, 'salary' => $salary, 'bonus' => $bonus, 'deduction' => $deduction, 'tax' => $tax, 'net' => $net, 'note' => $line['note'] ?? null]);

                $netBase = Rates::toBase($net, $employee->currency_id, $data['date']);
                $taxBase = Rates::toBase($tax, $employee->currency_id, $data['date']);
                $entry[] = ['account' => $employee, 'credit' => $net, 'base' => $netBase, 'memo' => __('Salary :period', ['period' => $data['period']])];
                $expense = Num::add($expense, Num::add($netBase, $taxBase));
                $taxTotal = Num::add($taxTotal, $taxBase);
            }

            if (! $entry) {
                throw new UserError(__('Choose at least one employee with a salary.'));
            }

            array_unshift($entry, ['account' => Account::system('salaries'), 'debit' => Num::money($expense)]);
            if (Num::isPositive($taxTotal)) {
                $entry[] = ['account' => Account::system('tax_payable'), 'credit' => Num::money($taxTotal)];
            }
            Ledger::post('payroll', $data['date'], __('Salaries of :period', ['period' => $data['period']]), $entry, $payroll);

            return $payroll;
        });

        return redirect()->route('payroll.show', ['id' => $payroll->id])->with('success', __('Salaries of :period saved.', ['period' => $payroll->period]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('hr.view');

        $payroll = Payroll::with('lines.employee.currency', 'lines.employee.type')->findOrFail($request->route('id'));

        return view('payroll.show', ['payroll' => $payroll, 'balances' => Ledger::balances($payroll->lines->pluck('employee_id')->all())]);
    }

    /** Take the payroll back, so the month can be done again. */
    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('hr.manage');

        $payroll = Payroll::findOrFail($request->route('id'));
        DB::transaction(function () use ($payroll) {
            Ledger::voidSource($payroll);
            $payroll->delete();
        });

        return redirect()->route('payroll.index')->with('success', __('Salaries of :period were taken back.', ['period' => $payroll->period]));
    }
}
