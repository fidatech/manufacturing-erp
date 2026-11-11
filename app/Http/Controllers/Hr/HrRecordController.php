<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\HrRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Human-resource records that are a simple list: leave, appraisals, contracts,
 * shifts, overtime, allowances and deductions. The address /hr/{screen} picks the list.
 * All of them live in one table (hr_records) and differ only in their boxes.
 */
class HrRecordController extends CrudController
{
    protected string $model = HrRecord::class;

    protected string $route = 'hr';

    protected string $permission = 'hr';

    protected string $title = 'Records';

    protected string $singular = 'Record';

    protected string $orderBy = 'date';

    protected string $orderDirection = 'desc';

    protected array $search = ['title', 'notes'];

    protected array $with = ['employee.currency'];

    /** screen => [title, singular, icon, stored types] */
    public const SCREENS = [
        'leave' => ['Leave Management', 'Leave', 'umbrella-beach', ['leave']],
        'appraisals' => ['Performance Appraisal', 'Appraisal', 'star', ['appraisal']],
        'contracts' => ['Employee Contracts', 'Contract', 'file-signature', ['contract']],
        'shifts' => ['Shift Scheduling', 'Shift', 'clock', ['shift']],
        'overtime' => ['Overtime Requests', 'Overtime Request', 'business-time', ['overtime']],
        'adjustments' => ['Allowances & Deductions', 'Allowance or Deduction', 'scale-unbalanced', ['allowance', 'deduction']],
    ];

    public static function statuses(): array
    {
        return ['requested' => __('Requested'), 'approved' => __('Approved'), 'rejected' => __('Rejected')];
    }

    protected function screen(): string
    {
        $screen = request()->route('screen');
        abort_unless(isset(self::SCREENS[$screen]), 404);
        [$this->title, $this->singular, $this->icon] = self::SCREENS[$screen];

        return $screen;
    }

    public function callAction($method, $parameters)
    {
        $this->screen();

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['screen' => $this->screen()];
    }

    protected function query(Request $request): Builder
    {
        return HrRecord::query()->whereIn('type', self::SCREENS[$this->screen()][3]);
    }

    protected function fields(?Model $record): array
    {
        $employee = ['name' => 'employee_id', 'label' => 'Employee', 'type' => 'select', 'search' => true, 'rules' => ['required', 'exists:accounts,id'],
            'options' => Account::ofType('employee')->active()->orderBy('name')->pluck('name', 'id')->all()];
        $notes = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12];
        $status = ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::statuses(), 'rules' => ['required', 'in:requested,approved,rejected'], 'default' => 'approved'];
        $from = ['name' => 'date', 'label' => 'From', 'type' => 'date', 'rules' => ['required', 'date'], 'default' => today_iso()];
        $to = ['name' => 'end_date', 'label' => 'To', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:date']];

        return match ($this->screen()) {
            'leave' => [$employee,
                ['name' => 'title', 'label' => 'Kind of leave', 'type' => 'select', 'rules' => ['required', 'string', 'max:150'],
                    'options' => ['Annual' => __('Annual'), 'Sick' => __('Sick'), 'Unpaid' => __('Unpaid'), 'Other' => __('Other')]],
                $from, ['name' => 'end_date', 'label' => 'To', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:date'], 'default' => today_iso()], $status, $notes],
            'appraisals' => [$employee,
                ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'rules' => ['required', 'date'], 'default' => today_iso()],
                ['name' => 'title', 'label' => 'Period', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: first half of the year'],
                ['name' => 'amount', 'label' => 'Score (1 to 5)', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:1', 'max:5']],
                $notes],
            'contracts' => [$employee,
                ['name' => 'title', 'label' => 'Kind of contract', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: permanent, one year, daily wage'],
                ['name' => 'date', 'label' => 'Starts on', 'type' => 'date', 'rules' => ['required', 'date'], 'default' => today_iso()],
                ['name' => 'end_date', 'label' => 'Ends on', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:date'], 'help' => 'Leave empty when it has no end.'],
                ['name' => 'amount', 'label' => 'Monthly salary in the contract', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
                $notes],
            'shifts' => [$employee,
                ['name' => 'title', 'label' => 'Shift', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: Day 8:00 to 16:00, Night guard'],
                $from, $to, $notes],
            'overtime' => [$employee,
                ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'rules' => ['required', 'date'], 'default' => today_iso()],
                ['name' => 'amount', 'label' => 'Hours', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0', 'max:24']],
                $status, $notes],
            'adjustments' => [$employee,
                ['name' => 'type', 'label' => 'Kind', 'type' => 'select', 'rules' => ['required', 'in:allowance,deduction'],
                    'options' => ['allowance' => __('Allowance (added to the salary)'), 'deduction' => __('Deduction (taken off the salary)')]],
                ['name' => 'title', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: transport, food, house rent, fine'],
                ['name' => 'amount', 'label' => 'Amount every month', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0'], 'help' => 'In the currency of the employee\'s account. The payroll adds it by itself.'],
                $from, ['name' => 'end_date', 'label' => 'Until', 'type' => 'date', 'rules' => ['nullable', 'date', 'after_or_equal:date'], 'help' => 'Leave empty when it continues.'],
                $notes],
        };
    }

    protected function columns(): array
    {
        $employee = ['Employee' => fn (HrRecord $r) => $r->employee->name];
        $status = ['Status' => fn (HrRecord $r) => self::statuses()[$r->status] ?? ''];
        $period = ['From' => fn (HrRecord $r) => fmt_date($r->date), 'To' => fn (HrRecord $r) => fmt_date($r->end_date)];

        return match ($this->screen()) {
            'leave' => $employee + ['Kind of leave' => fn (HrRecord $r) => __($r->title)] + $period
                + ['Days' => fn (HrRecord $r) => $r->end_date ? $r->date->diffInDays($r->end_date) + 1 : 1] + $status,
            'appraisals' => $employee + ['Date' => fn (HrRecord $r) => fmt_date($r->date), 'Period' => fn (HrRecord $r) => $r->title, 'Score' => fn (HrRecord $r) => qty($r->amount).' / 5'],
            'contracts' => $employee + ['Kind of contract' => fn (HrRecord $r) => $r->title, 'Starts on' => fn (HrRecord $r) => fmt_date($r->date), 'Ends on' => fn (HrRecord $r) => fmt_date($r->end_date),
                'Salary' => fn (HrRecord $r) => $r->amount !== null ? money($r->amount, $r->employee->currency->code) : ''],
            'shifts' => $employee + ['Shift' => fn (HrRecord $r) => $r->title] + $period,
            'overtime' => $employee + ['Date' => fn (HrRecord $r) => fmt_date($r->date), 'Hours' => fn (HrRecord $r) => qty($r->amount)] + $status,
            'adjustments' => $employee + ['Kind' => fn (HrRecord $r) => $r->type === 'allowance' ? __('Allowance') : __('Deduction'), 'Name' => fn (HrRecord $r) => $r->title,
                'Amount every month' => fn (HrRecord $r) => money($r->amount, $r->employee->currency->code)] + $period,
        };
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $screen = $this->screen();
        if ($screen !== 'adjustments') {
            $data['type'] = self::SCREENS[$screen][3][0];
        }
        if (! $record) {
            $data['created_by'] = $request->user()->id;
        }

        return $data;
    }
}
