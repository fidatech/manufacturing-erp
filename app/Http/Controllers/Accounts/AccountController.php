<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;
use App\Services\Ledger;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Customers, suppliers, banks, sarafis, shareholders, employees, warehouse managers,
 * cash boxes, loans, income and expense names: all are accounts of a kind.
 * The address /accounts/{type} decides which kind this screen shows.
 */
class AccountController extends CrudController
{
    protected string $model = Account::class;

    protected string $route = 'accounts';

    protected string $permission = 'accounts';

    protected string $title = 'Accounts';

    protected string $singular = 'Account';

    protected array $search = ['name', 'phone', 'company'];

    protected array $with = ['currency', 'type'];

    private ?AccountType $type = null;

    private ?array $balances = null;

    /** Screen titles and icons of the kinds that come with the system. */
    private const SCREENS = [
        'customer' => ['Customers', 'Customer', 'user-group'],
        'supplier' => ['Suppliers', 'Supplier', 'truck-field'],
        'bank' => ['Banks', 'Bank Account', 'building-columns'],
        'sarafi' => ['Sarafi', 'Sarafi', 'money-bill-transfer'],
        'cash' => ['Cash Boxes', 'Cash Box', 'vault'],
        'shareholder' => ['Shareholders', 'Shareholder', 'handshake'],
        'employee' => ['Employees', 'Employee', 'id-badge'],
        'warehouse_manager' => ['Warehouse Managers', 'Warehouse Manager', 'user-tie'],
        'liability' => ['Loans & Liabilities', 'Loan / Liability', 'hand-holding-dollar'],
        'expense' => ['Expense Categories', 'Expense Category', 'receipt'],
        'income' => ['Income Categories', 'Income Category', 'sack-dollar'],
    ];

    protected function type(): AccountType
    {
        if ($this->type === null) {
            $this->type = AccountType::where('key', request()->route('type'))->where('is_system', false)->firstOrFail();
            [$this->title, $this->singular, $this->icon] = self::SCREENS[$this->type->key] ?? [$this->type->name, $this->type->name, 'folder'];
            if ($this->type->key === 'employee') {
                $this->permission = 'hr';
            }
        }

        return $this->type;
    }

    public function callAction($method, $parameters)
    {
        // nothing is remembered from an earlier request handled by this same object
        $this->type = null;
        $this->balances = null;
        $this->permission = 'accounts';
        $this->type();

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['type' => $this->type()->key];
    }

    protected function query(Request $request): Builder
    {
        return Account::query()->where('account_type_id', $this->type()->id)->whereNull('system_key');
    }

    protected function fields(?Model $record): array
    {
        $type = $this->type();
        $used = $record && $record->lines()->exists();
        $nameLabel = in_array($type->key, ['expense', 'income', 'cash', 'bank'], true) ? 'Name' : 'Full name';

        $currencies = Currency::where('is_active', true)->orderByDesc('is_base')->orderBy('code')->pluck('code', 'id')->all();
        $withOpening = ! in_array($type->nature, ['income', 'expense'], true);

        $fields = [['name' => 'name', 'label' => $nameLabel, 'rules' => ['required', 'string', 'max:255'], 'col' => $record ? 6 : 12]];
        if ($record) {
            $fields[] = ['name' => 'currency_id', 'label' => 'Currency of this account', 'type' => 'select', 'readonly' => $used, 'options' => $currencies,
                'rules' => ['required', 'exists:currencies,id'],
                'help' => $used ? 'The currency cannot change after the first transaction.' : 'All money of this account is counted in this currency.'];
        } else {
            // a new account: tick every currency it works in; one account is made for each
            $fields[] = ['name' => 'currencies', 'label' => 'Currencies', 'type' => 'currencies', 'virtual' => true, 'col' => 12, 'options' => $currencies,
                'sides' => $withOpening ? self::sides($type) : null, 'side' => self::defaultSide($type),
                'default' => Currency::where('is_base', true)->value('id'), 'rules' => ['nullable', 'array'],
                'help' => $withOpening
                    ? 'Tick every currency this account works in. A separate account is made for each. An opening balance is optional: write it only if there is one, and choose what it means in that currency.'
                    : 'Tick every currency this account works in. A separate account is made for each.'];
            $fields[] = ['name' => 'currency_id', 'label' => 'Currency', 'hidden' => true, 'rules' => ['required', 'exists:currencies,id']];
        }

        if ($type->is_party) {
            $fields[] = ['name' => 'phone', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:50']];
            if (! in_array($type->key, ['employee', 'warehouse_manager'], true)) {
                $fields[] = ['name' => 'company', 'label' => 'Company name', 'rules' => ['nullable', 'string', 'max:255']];
            }
            $fields[] = ['name' => 'address', 'label' => 'Address', 'rules' => ['nullable', 'string', 'max:255'], 'col' => 12];
        }

        foreach (self::extraFields($type->key) as $field) {
            $fields[] = $field + ['meta' => true];
        }

        $fields[] = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12];
        $fields[] = ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true, 'help' => 'Switch off to stop using it. It disappears from pick lists, but its history stays.'];

        if (! $record && $withOpening) {
            // filled in for each ticked currency by store()
            $fields[] = ['name' => 'opening_amount', 'label' => 'Opening balance', 'hidden' => true, 'virtual' => true, 'rules' => ['nullable', 'numeric', 'min:0']];
            $fields[] = ['name' => 'opening_side', 'label' => 'Opening balance is', 'hidden' => true, 'virtual' => true, 'rules' => ['nullable', 'in:debit,credit']];
        }

        return $fields;
    }

    /** What an opening balance can mean, by kind of account. */
    public static function sides(AccountType $type): array
    {
        if ($type->is_money) {
            return ['debit' => __('We have it'), 'credit' => __('We owe it')];
        }
        if ($type->key === 'shareholder') {
            return ['credit' => __('Capital (payable to them)'), 'debit' => __('Receivable (they owe us)')];
        }

        return ['debit' => __('Receivable (they owe us)'), 'credit' => __('Payable (we owe them)')];
    }

    /** The usual meaning: suppliers, employees, loans and owners are owed by us; everyone else owes us. */
    public static function defaultSide(AccountType $type): string
    {
        return in_array($type->nature, ['liability', 'equity'], true) ? 'credit' : 'debit';
    }

    /** Extra boxes a kind of account needs. They are kept in the "meta" column. */
    private static function extraFields(string $key): array
    {
        return match ($key) {
            'bank' => [
                ['name' => 'bank_name', 'label' => 'Bank', 'rules' => ['nullable', 'string', 'max:150']],
                ['name' => 'account_number', 'label' => 'Account number', 'rules' => ['nullable', 'string', 'max:100']],
            ],
            'employee' => [
                ['name' => 'father_name', 'label' => 'Father name', 'rules' => ['nullable', 'string', 'max:150']],
                ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => \App\Models\Lookup::options('department'), 'rules' => ['nullable', 'string', 'max:150']],
                ['name' => 'job_title', 'label' => 'Designation', 'type' => 'select', 'options' => \App\Models\Lookup::designations(), 'rules' => ['nullable', 'string', 'max:150'],
                    'help' => 'Add departments and designations under Human Resources first.'],
                ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['male' => __('Male'), 'female' => __('Female')], 'rules' => ['nullable', 'in:male,female']],
                ['name' => 'id_number', 'label' => 'Tazkira number', 'rules' => ['nullable', 'string', 'max:100']],
                ['name' => 'tax_number', 'label' => 'Tax number (TIN)', 'rules' => ['nullable', 'string', 'max:100']],
                ['name' => 'join_date', 'label' => 'Started work on', 'type' => 'date', 'rules' => ['nullable', 'date']],
                ['name' => 'salary', 'label' => 'Monthly salary', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0'], 'help' => 'In the currency of this account.'],
                ['name' => 'salary_tax', 'label' => 'Monthly salary tax', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0']],
            ],
            'shareholder' => [
                ['name' => 'share_percent', 'label' => 'Share of the company (%)', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0', 'max:100']],
            ],
            default => [],
        };
    }

    /**
     * A new account is saved once for every ticked currency, each with its own
     * optional opening balance. All of them are saved together or not at all.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        if (! $request->has('currencies')) {
            return parent::store($request);
        }
        \Illuminate\Support\Facades\Gate::authorize($this->permission.'.manage');

        $ticked = [];
        foreach ((array) $request->input('currencies') as $currencyId => $row) {
            if (! empty($row['on'])) {
                $ticked[(int) $currencyId] = ['opening' => $row['opening'] ?? null, 'side' => $row['side'] ?? null];
            }
        }
        if (! $ticked) {
            throw \Illuminate\Validation\ValidationException::withMessages(['currencies' => __('Tick at least one currency.')]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $ticked) {
            // each currency has its own balance and its own meaning: receivable in one, payable in another
            foreach ($ticked as $currencyId => $row) {
                $request->merge(['currency_id' => $currencyId, 'opening_amount' => $row['opening'], 'opening_side' => $row['side']]);
                $this->save($request, null);
            }
        });

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', count($ticked) > 1
            ? __(':count accounts saved, one for each currency.', ['count' => count($ticked)])
            : __(':name saved.', ['name' => __($this->singular)]));
    }

    protected function columns(): array
    {
        $type = $this->type();
        $columns = [
            'Name' => fn (Account $a) => $a->name.($a->company ? ' · '.$a->company : ''),
            'Currency' => fn (Account $a) => $a->currency->code,
        ];

        if ($type->is_party) {
            $columns['Phone'] = fn (Account $a) => $a->phone;
        }
        if ($type->key === 'employee') {
            $columns['Department'] = fn (Account $a) => $a->metaValue('department');
            $columns['Designation'] = fn (Account $a) => $a->metaValue('job_title');
            $columns['Monthly salary'] = fn (Account $a) => $a->metaValue('salary') ? money($a->metaValue('salary'), $a->currency->code) : '';
        }
        $columns['Balance'] = fn (Account $a) => $a->balanceHtml($this->balanceOf($a));
        $columns['Active'] = fn (Account $a) => $a->is_active ? __('Yes') : __('No');

        return $columns;
    }

    private function balanceOf(Account $account): string
    {
        $this->balances ??= Ledger::balances();

        return $this->balances[$account->id]['own'] ?? '0.00';
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Statement'), 'url' => route('statement', ['id' => $record->id])]];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $data['account_type_id'] = $this->type()->id;
        }

        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $amount = Num::money($virtual['opening_amount'] ?? 0);
        if (! $created || ! Num::isPositive($amount)) {
            return;
        }

        $side = ($virtual['opening_side'] ?? null) ?: self::defaultSide($this->type());
        $base = \App\Services\Rates::toBase($amount, $record->currency_id, today_iso());
        Ledger::post('opening', today_iso(), __('Opening balance').': '.$record->name, [
            ['account' => $record, $side => $amount, 'base' => $base],
            ['account' => Account::system('opening'), ($side === 'debit' ? 'credit' : 'debit') => $base],
        ], $record);
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->lines()->exists() ? __('This account already has transactions. Switch it off instead of deleting it.') : null;
    }
}
