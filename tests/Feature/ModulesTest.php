<?php

namespace Tests\Feature;

use App\Http\Controllers\Hr\HrRecordController;
use App\Http\Controllers\Setup\LookupController;
use App\Models\Account;
use App\Models\HrRecord;
use App\Models\Item;
use App\Models\Lookup;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Ledger;
use App\Services\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
    }

    public function test_short_lists_departments_designations_document_types_asset_categories(): void
    {
        foreach (array_keys(LookupController::LISTS) as $type) {
            $this->get(route('lookups.index', ['type' => $type]))->assertOk();
            $this->get(route('lookups.create', ['type' => $type]))->assertOk();
            // a designation needs its department (departments come first in the list of lists)
            $extra = $type === 'designation' ? ['parent_id' => Lookup::where('type', 'department')->value('id')] : [];
            $this->post(route('lookups.store', ['type' => $type]), ['name' => 'Production'] + $extra)->assertRedirect(route('lookups.index', ['type' => $type]));
        }

        // a designation without a department is refused; the same job may exist in two departments
        $this->post(route('lookups.store', ['type' => 'designation']), ['name' => 'Operator'])->assertSessionHasErrors('parent_id');
        $this->post(route('lookups.store', ['type' => 'department']), ['name' => 'Sales'])->assertRedirect();
        $production = Lookup::where('type', 'department')->where('name', 'Production')->value('id');
        $sales = Lookup::where('type', 'department')->where('name', 'Sales')->value('id');
        $this->post(route('lookups.store', ['type' => 'designation']), ['name' => 'Manager', 'parent_id' => $production])->assertRedirect();
        $this->post(route('lookups.store', ['type' => 'designation']), ['name' => 'Manager', 'parent_id' => $sales])->assertRedirect();
        $this->post(route('lookups.store', ['type' => 'designation']), ['name' => 'Manager', 'parent_id' => $sales])->assertSessionHasErrors('name');
        $this->assertSame(2, Lookup::where('type', 'designation')->where('name', 'Manager')->count());
        $this->get(route('lookups.index', ['type' => 'designation']))->assertOk()->assertSee('Sales');
        $this->assertSame(count(LookupController::LISTS), Lookup::where('name', 'Production')->count());

        // the same name twice in one list is refused
        $this->post(route('lookups.store', ['type' => 'department']), ['name' => 'Production'])->assertSessionHasErrors('name');

        // the lists feed the forms
        $this->get(route('accounts.create', ['type' => 'employee']))->assertOk()->assertSee('Production');
        $this->get(route('assets.create'))->assertOk()->assertSee('Production');
        $this->get(route('documents.index'))->assertOk()->assertSee('Production');
    }

    public function test_human_resource_screens_keep_records(): void
    {
        $employee = $this->account('employee', 'Abdul Wahab', 'AFN', ['meta' => ['salary' => '8000']]);

        foreach (array_keys(HrRecordController::SCREENS) as $screen) {
            $this->get(route('hr.index', ['screen' => $screen]))->assertOk();
            $this->get(route('hr.create', ['screen' => $screen]))->assertOk()->assertSee('Abdul Wahab');
        }

        $this->post(route('hr.store', ['screen' => 'leave']), ['employee_id' => $employee->id, 'title' => 'Sick', 'date' => '2026-10-05', 'end_date' => '2026-10-07', 'status' => 'approved'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'appraisals']), ['employee_id' => $employee->id, 'date' => '2026-10-01', 'title' => 'First half', 'amount' => '4'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'contracts']), ['employee_id' => $employee->id, 'title' => 'One year', 'date' => '2026-01-01', 'end_date' => '2026-12-31', 'amount' => '8000'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'shifts']), ['employee_id' => $employee->id, 'title' => 'Night guard', 'date' => '2026-10-01'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'overtime']), ['employee_id' => $employee->id, 'date' => '2026-10-10', 'amount' => '3', 'status' => 'requested'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'adjustments']), ['employee_id' => $employee->id, 'type' => 'allowance', 'title' => 'Transport', 'amount' => '500', 'date' => '2026-01-01'])->assertRedirect();
        $this->post(route('hr.store', ['screen' => 'adjustments']), ['employee_id' => $employee->id, 'type' => 'deduction', 'title' => 'Old fine', 'amount' => '100', 'date' => '2026-01-01', 'end_date' => '2026-03-31'])->assertRedirect();

        $this->assertSame(7, HrRecord::count());
        $this->get(route('hr.index', ['screen' => 'leave']))->assertOk()->assertSee('Abdul Wahab')->assertSee('Sick');
        $this->get(route('hr.index', ['screen' => 'appraisals']))->assertOk()->assertSee('4 / 5');
        $this->get(route('hr.index', ['screen' => 'adjustments']))->assertOk()->assertSee('Transport')->assertSee('Old fine');
        $this->get(route('hr.index', ['screen' => 'shifts']))->assertOk()->assertDontSee('Transport');

        // a leave cannot end before it starts
        $this->post(route('hr.store', ['screen' => 'leave']), ['employee_id' => $employee->id, 'title' => 'Sick', 'date' => '2026-10-05', 'end_date' => '2026-10-01', 'status' => 'approved'])->assertSessionHasErrors('end_date');

        // payroll of October fills in the allowance that is still running, not the deduction that ended in March
        $this->get(route('payroll.create', ['period' => '2026-10']))->assertOk()->assertSee('value="500.00"', false)->assertDontSee('value="100.00"', false);

        $record = HrRecord::where('type', 'shift')->firstOrFail();
        $this->get(route('hr.edit', ['screen' => 'shifts', 'id' => $record->id]))->assertOk()->assertSee('Night guard');
        $this->delete(route('hr.destroy', ['screen' => 'shifts', 'id' => $record->id]))->assertRedirect();
        $this->assertSame(6, HrRecord::count());
    }

    public function test_daily_attendance_sheet(): void
    {
        $a = $this->account('employee', 'Abdul Wahab', 'AFN');
        $b = $this->account('employee', 'Khalid Khan', 'AFN');
        $today = now()->toDateString();

        $this->get(route('attendance.index'))->assertOk()->assertSee('Abdul Wahab');
        $this->post(route('attendance.store'), ['date' => $today, 'rows' => [$a->id => ['status' => 'present'], $b->id => ['status' => 'absent', 'notes' => 'Sick']]])->assertRedirect();
        $this->assertSame(2, HrRecord::where('type', 'attendance')->count());

        // saving the same day again changes the marks instead of adding new ones
        $this->post(route('attendance.store'), ['date' => $today, 'rows' => [$a->id => ['status' => 'half'], $b->id => ['status' => '']]])->assertRedirect();
        $this->assertSame(1, HrRecord::where('type', 'attendance')->count());
        $this->assertSame('half', HrRecord::where('type', 'attendance')->first()->status);
        $this->get(route('attendance.index', ['date' => $today]))->assertOk();

        // tomorrow cannot be marked yet
        $this->post(route('attendance.store'), ['date' => now()->addDay()->toDateString(), 'rows' => [$a->id => ['status' => 'present']]])->assertSessionHasErrors('date');
    }

    public function test_sale_order_supplier_quotation_and_customer_price_list(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main warehouse']);
        $supplier = $this->account('supplier', 'Javed', 'USD');
        $customer = $this->account('customer', 'Shaheer', 'USD');
        $mango = Item::create(['type' => 'product', 'name' => 'Mango KC9012', 'unit_id' => Unit::where('name', 'Can')->value('id'), 'sale_price' => '75']);
        $line = [['item_id' => $mango->id, 'qty' => '10', 'price' => '40']];
        $common = ['date' => '2026-10-01', 'warehouse_id' => $warehouse->id];

        // supplier quotation -> purchase order -> purchase
        $this->post(route('purchases.store'), $common + ['type' => 'quotation', 'supplier_id' => $supplier->id, 'lines' => $line])->assertRedirect();
        $quotation = Purchase::where('type', 'quotation')->firstOrFail();
        $this->assertStringStartsWith('PQ-', $quotation->number);
        $this->get(route('purchases.show', ['id' => $quotation->id]))->assertOk()->assertSee('Turn into an order');
        $this->post(route('purchases.store'), $common + ['type' => 'order', 'supplier_id' => $supplier->id, 'parent_id' => $quotation->id, 'lines' => $line])->assertRedirect();
        $order = Purchase::where('type', 'order')->firstOrFail();
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame('0.0000', Stock::onHand($mango->id));
        $this->post(route('purchases.store'), $common + ['type' => 'invoice', 'supplier_id' => $supplier->id, 'parent_id' => $order->id, 'received' => '1', 'lines' => $line])->assertRedirect();
        $this->assertSame('converted', $order->fresh()->status);
        $this->assertSame('10.0000', Stock::onHand($mango->id));
        $this->get(route('purchases.index', ['type' => 'invoice', 'view' => 'grn']))->assertOk()->assertSee('GRN-000001');

        // this customer has his own price
        $this->get(route('price-lists.create'))->assertOk();
        $this->post(route('price-lists.store'), ['customer_id' => $customer->id, 'item_id' => $mango->id, 'price' => '65'])->assertRedirect(route('price-lists.index'));
        $this->post(route('price-lists.store'), ['customer_id' => $customer->id, 'item_id' => $mango->id, 'price' => '60'])->assertSessionHasErrors('item_id');
        $this->get(route('price-lists.index'))->assertOk()->assertSee('Shaheer')->assertSee('65 USD');
        $this->get(route('sales.create', ['type' => 'invoice']))->assertOk()->assertSee('&quot;'.$mango->id.'&quot;:&quot;65&quot;', false);

        // sale order -> sale invoice
        $sold = [['item_id' => $mango->id, 'qty' => '4', 'price' => '65']];
        $this->post(route('sales.store'), $common + ['type' => 'order', 'customer_id' => $customer->id, 'lines' => $sold])->assertRedirect();
        $saleOrder = Sale::where('type', 'order')->firstOrFail();
        $this->assertStringStartsWith('SO-', $saleOrder->number);
        $this->assertSame('10.0000', Stock::onHand($mango->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));
        $this->get(route('sales.show', ['id' => $saleOrder->id]))->assertOk()->assertSee('Turn into a sale');
        $this->get(route('sales.print', ['id' => $saleOrder->id]))->assertOk()->assertSee('Sale Order');

        $this->post(route('sales.store'), $common + ['type' => 'invoice', 'customer_id' => $customer->id, 'parent_id' => $saleOrder->id, 'lines' => $sold])->assertRedirect();
        $this->assertSame('converted', $saleOrder->fresh()->status);
        $this->assertSame('6.0000', Stock::onHand($mango->id));
        $this->assertSame('260.00', Ledger::balance($customer->id));

        $invoice = Sale::where('type', 'invoice')->firstOrFail();
        $this->get(route('sales.index', ['type' => 'invoice', 'view' => 'delivery']))->assertOk()->assertSee('Delivery notes')->assertSee($invoice->number);
        $this->get(route('sales.index', ['type' => 'invoice', 'view' => 'dispatch']))->assertOk()->assertSee('Dispatch planning')->assertSee($invoice->number);
        $this->post(route('sales.delivery', ['id' => $invoice->id]), ['delivery_status' => 'delivered']);
        $this->get(route('sales.index', ['type' => 'invoice', 'view' => 'dispatch']))->assertOk()->assertDontSee($invoice->number);

        // reports about people
        $this->get(route('reports.parties', ['group' => 'customers']))->assertOk()->assertSee('Shaheer');
        $this->get(route('reports.parties', ['group' => 'suppliers']))->assertOk()->assertSee('Javed');
        $this->get(route('reports.parties', ['group' => 'debtors']))->assertOk()->assertSee('Shaheer')->assertDontSee('Javed');
        $this->get(route('reports.parties', ['group' => 'creditors']))->assertOk()->assertSee('Javed')->assertDontSee('Shaheer');
        $this->get(route('reports.parties', ['group' => 'shareholders']))->assertOk();
    }

    public function test_exchange_transfer_petty_cash_and_permissions_pages(): void
    {
        $this->rate('AFN', '70');
        $this->get(route('money.create', ['kind' => 'transfer', 'group' => 'exchange']))->assertOk()->assertSee('Currency exchange');
        $this->get(route('money.create', ['kind' => 'transfer', 'group' => 'transfer']))->assertOk()->assertSee('Transfer between accounts');

        $this->post(route('money.store', ['kind' => 'transfer']), ['group' => 'exchange', 'date' => '2026-10-27', 'money_id' => $this->cash('USD')->id, 'other_id' => $this->cash('AFN')->id, 'amount' => '100', 'other_amount' => '7050'])->assertRedirect();
        $this->assertSame('7050.00', Ledger::balance($this->cash('AFN')->id));

        // the cash boxes are marked so the form can pick the one of the chosen person's currency
        $this->get(route('money.create', ['kind' => 'in']))->assertOk()->assertSee('data-auto-money', false)->assertSee('data-cash', false);
        $this->get(route('money.create', ['kind' => 'out']))->assertOk()->assertSee('data-auto-money', false);
        $this->get(route('money.create', ['kind' => 'expense']))->assertOk()->assertDontSee('data-auto-money', false);

        $this->get(route('petty-cash'))->assertOk()->assertSee('Cash AFN')->assertSee('7,050.00')->assertSee('Currency exchange');
        $this->get(route('permissions'))->assertOk()->assertSee('Administrator');
        $this->get(route('assets.write-off', ['kind' => 'depreciation']))->assertOk();
        $this->get(route('assets.write-off', ['kind' => 'amortization']))->assertOk();
        $this->get(route('money.index', ['type' => 'expense']))->assertOk();
    }

    public function test_depreciation_and_amortization_are_run_separately(): void
    {
        $usd = $this->currency('USD')->id;
        $this->post(route('assets.store'), ['name' => 'Machine', 'class' => 'fixed', 'kind' => 'tangible', 'category' => 'Machines', 'purchase_date' => '2026-01-01', 'cost' => '1200', 'currency_id' => $usd, 'life_months' => '12']);
        $this->post(route('assets.store'), ['name' => 'Licence', 'class' => 'fixed', 'kind' => 'intangible', 'purchase_date' => '2026-01-01', 'cost' => '600', 'currency_id' => $usd, 'life_months' => '12']);

        $this->post(route('assets.depreciate'), ['period' => '2026-01', 'kind' => 'tangible'])->assertSessionHas('success');
        $this->assertSame('100.00', Ledger::balance(Account::system('depreciation')->id));
        $this->post(route('assets.depreciate'), ['period' => '2026-01', 'kind' => 'intangible'])->assertSessionHas('success');
        $this->assertSame('150.00', Ledger::balance(Account::system('depreciation')->id));

        $this->get(route('assets.write-off', ['kind' => 'depreciation']))->assertOk()->assertSee('Machine')->assertDontSee('Licence');
        $this->get(route('assets.index'))->assertOk()->assertSee('Machines');

        // taking back amortization leaves depreciation alone
        $this->post(route('assets.undo'), ['period' => '2026-01', 'kind' => 'intangible'])->assertSessionHas('success');
        $this->assertSame('100.00', Ledger::balance(Account::system('depreciation')->id));
    }

    public function test_a_new_account_is_made_once_for_every_ticked_currency(): void
    {
        $this->rate('AFN', '70');
        $usd = $this->currency('USD')->id;
        $afn = $this->currency('AFN')->id;
        $pkr = $this->currency('PKR')->id;

        $this->get(route('accounts.create', ['type' => 'customer']))->assertOk()->assertSee('currencies['.$afn.'][on]', false);

        // two currencies ticked, an opening balance only in afghani
        $this->post(route('accounts.store', ['type' => 'customer']), [
            'name' => 'Haji Sharif', 'phone' => '0700000000', 'is_active' => '1',
            'currencies' => [$usd => ['on' => '1', 'opening' => '', 'side' => 'debit'], $afn => ['on' => '1', 'opening' => '7000', 'side' => 'debit'], $pkr => ['opening' => '']],
        ])->assertRedirect(route('accounts.index', ['type' => 'customer']));

        $accounts = Account::where('name', 'Haji Sharif')->with('currency')->get()->keyBy(fn ($a) => $a->currency->code);
        $this->assertSame(['AFN', 'USD'], $accounts->keys()->sort()->values()->all());
        $this->assertSame('7000.00', Ledger::balance($accounts['AFN']->id));
        $this->assertSame('0.00', Ledger::balance($accounts['USD']->id));

        // he owes us in dollars, and we owe him in afghani
        $this->get(route('accounts.create', ['type' => 'customer']))->assertOk()->assertSee('currencies['.$afn.'][side]', false)->assertSee('Receivable')->assertSee('Payable');
        $this->post(route('accounts.store', ['type' => 'customer']), ['name' => 'Zaki Trading', 'is_active' => '1',
            'currencies' => [$usd => ['on' => '1', 'opening' => '300', 'side' => 'debit'], $afn => ['on' => '1', 'opening' => '14000', 'side' => 'credit']],
        ])->assertRedirect();
        $zaki = Account::where('name', 'Zaki Trading')->with('currency')->get()->keyBy(fn ($a) => $a->currency->code);
        $this->assertSame('300.00', Ledger::balance($zaki['USD']->id));
        $this->assertSame('-14000.00', Ledger::balance($zaki['AFN']->id));
        $this->get(route('accounts.index', ['type' => 'customer', 'q' => 'Zaki']))->assertOk()->assertSee('owes us')->assertSee('we owe');

        // a supplier's balance is payable unless told otherwise
        $this->post(route('accounts.store', ['type' => 'supplier']), ['name' => 'Javed', 'is_active' => '1', 'currencies' => [$usd => ['on' => '1', 'opening' => '50']]])->assertRedirect();
        $this->assertSame('-50.00', Ledger::balance(Account::where('name', 'Javed')->value('id')));
        $this->get(route('accounts.index', ['type' => 'customer']))->assertOk()->assertSee('Haji Sharif');

        // no currency ticked: nothing is made
        $this->post(route('accounts.store', ['type' => 'supplier']), ['name' => 'Nobody', 'is_active' => '1', 'currencies' => [$usd => ['opening' => '5']]])->assertSessionHasErrors('currencies');
        $this->assertDatabaseMissing('accounts', ['name' => 'Nobody']);

        // one currency has no exchange rate: none of the accounts is made
        $this->post(route('accounts.store', ['type' => 'supplier']), ['name' => 'Half', 'is_active' => '1',
            'currencies' => [$usd => ['on' => '1', 'opening' => '10'], $pkr => ['on' => '1', 'opening' => '500']]])->assertSessionHas('danger');
        $this->assertDatabaseMissing('accounts', ['name' => 'Half']);

        // an expense category has no opening balance
        $this->post(route('accounts.store', ['type' => 'expense']), ['name' => 'Fuel', 'is_active' => '1', 'currencies' => [$usd => ['on' => '1']]])->assertRedirect();
        $this->assertDatabaseHas('accounts', ['name' => 'Fuel', 'currency_id' => $usd]);
    }

    public function test_amounts_typed_with_thousands_commas_are_stored_as_plain_numbers(): void
    {
        $this->rate('AFN', '70');
        $usd = $this->currency('USD')->id;
        $afn = $this->currency('AFN')->id;

        // opening balances with commas, and a name that only looks like a number
        $this->post(route('accounts.store', ['type' => 'customer']), ['name' => '1,000', 'is_active' => '1',
            'currencies' => [$usd => ['on' => '1', 'opening' => '12,500.75', 'side' => 'debit'], $afn => ['on' => '1', 'opening' => '1,400,000', 'side' => 'credit']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $accounts = Account::where('name', '1,000')->with('currency')->get()->keyBy(fn ($a) => $a->currency->code);
        $this->assertCount(2, $accounts);                                   // the name was left alone
        $this->assertSame('12500.75', Ledger::balance($accounts['USD']->id));
        $this->assertSame('-1400000.00', Ledger::balance($accounts['AFN']->id));

        // the same on every other amount box
        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $this->cash('USD')->id, 'other_id' => $accounts['USD']->id, 'amount' => '2,500.75', 'description' => '1,000'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('10000.00', Ledger::balance($accounts['USD']->id));
        $this->assertDatabaseHas('journal_entries', ['description' => '1,000']);

        // text is still refused where a number is needed
        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $this->cash('USD')->id, 'other_id' => $accounts['USD']->id, 'amount' => 'ten'])->assertSessionHasErrors('amount');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $this->cash('USD')->id, 'other_id' => $accounts['USD']->id, 'amount' => '1,25'])->assertSessionHasErrors('amount');
    }

    public function test_the_capital_page_adds_lists_and_totals_capital(): void
    {
        $owner = $this->account('shareholder', 'Partner A', 'USD', ['meta' => ['share_percent' => '100']]);
        $customer = $this->account('customer', 'Zaki', 'USD');
        $cash = $this->cash('USD');

        // only shareholders can be chosen
        $this->get(route('capital'))->assertOk()->assertSee('Partner A')->assertDontSee('Zaki');

        $this->post(route('money.store', ['kind' => 'in']), ['group' => 'shareholder', 'return' => 'capital', 'date' => '2026-01-01', 'money_id' => $cash->id, 'other_id' => $owner->id, 'amount' => '10000'])
            ->assertRedirect(route('capital'));
        $this->post(route('money.store', ['kind' => 'out']), ['group' => 'shareholder', 'date' => '2026-02-01', 'money_id' => $cash->id, 'other_id' => $owner->id, 'amount' => '1500']);
        $this->post(route('money.profit.store'), ['date' => '2026-03-01', 'amount' => '700']);

        $this->get(route('capital'))->assertOk()
            ->assertSee('10,000.00')      // capital put in, and the row in the list
            ->assertSee('1,500.00')       // taken out
            ->assertSee('700.00')         // profit shared
            ->assertSee('9,200.00');      // owners' money in the company now
    }

    public function test_only_the_page_being_shown_is_highlighted_in_the_menu(): void
    {
        $pages = [
            [route('accounts.index', ['type' => 'customer']), 'Customers'],
            [route('accounts.create', ['type' => 'supplier']), 'Suppliers'],
            [route('sales.index', ['type' => 'invoice']), 'Sale Invoices'],
            [route('sales.index', ['type' => 'invoice', 'view' => 'delivery']), 'Delivery Notes'],
            [route('sales.index', ['type' => 'invoice', 'view' => 'dispatch']), 'Dispatch Planning'],
            [route('purchases.index', ['type' => 'invoice', 'view' => 'grn']), 'Goods Received Notes'],
            [route('money.index'), 'Transactions'],
            [route('money.index', ['type' => 'expense']), 'Expenses'],
            [route('money.create', ['kind' => 'in']), 'Money In'],
            [route('money.create', ['kind' => 'out', 'group' => 'shareholder']), 'Withdrawals'],
            [route('money.create', ['kind' => 'transfer', 'group' => 'exchange']), 'Currency Exchange'],
            [route('capital'), 'Capital'],
            [route('capital', ['from' => 'shareholders']), 'Capital Contribution'],
            [route('items.index', ['type' => 'raw']), 'Raw Materials'],
            [route('items.index', ['type' => 'product', 'from' => 'manufacturing']), 'Final Products'],
            [route('stock.index'), 'Stock in Hand'],
            [route('stock.index', ['from' => 'reports', 'q' => 'mango']), 'Stock in Hands'],
            [route('chart'), 'Chart of Accounts'],
            [route('hr.index', ['screen' => 'leave']), 'Leave Management'],
            [route('lookups.index', ['type' => 'designation']), 'Designations'],
            [route('accounts.index', ['type' => 'employee']), 'Employees'],
        ];

        foreach ($pages as [$url, $label]) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'menu-sublink active'), "More or less than one active entry on $url");
            $this->assertMatchesRegularExpression('/menu-sublink active" href="[^"]*">'.preg_quote($label, '/').'</', $html, "Wrong entry is active on $url");
        }

        // the Employees page is no longer listed under Accounts
        $this->assertSame(1, substr_count($this->get(route('dashboard'))->getContent(), '>Employees<'));
    }

    public function test_the_company_logo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');

        $this->get(route('company.edit'))->assertOk();
        $this->get(route('company.logo'))->assertNotFound();

        $this->put(route('company.update'), ['company_name' => 'Kanozay Foods', 'logo' => UploadedFile::fake()->image('logo.png', 120, 60)])->assertRedirect();
        $this->get(route('company.logo'))->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee('Kanozay Foods')->assertSee('setup/company/logo', false);

        // a file that is not a picture is refused
        $this->put(route('company.update'), ['logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf')])->assertSessionHasErrors('logo');

        $this->put(route('company.update'), ['company_name' => 'Kanozay Foods', 'remove_logo' => '1'])->assertRedirect();
        $this->get(route('company.logo'))->assertNotFound();
    }
}
