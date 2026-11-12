<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Item;
use App\Models\Payroll;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
    }

    public function test_an_asset_loses_value_every_month_and_can_be_sold(): void
    {
        $cash = $this->cash('USD');
        $this->get(route('assets.index'))->assertOk();
        $this->get(route('assets.create'))->assertOk();

        $this->post(route('assets.store'), [
            'name' => 'Filling machine', 'class' => 'fixed', 'kind' => 'tangible', 'purchase_date' => '2026-01-10', 'quantity' => '1',
            'cost' => '12000', 'currency_id' => $this->currency('USD')->id, 'paid_from_id' => $cash->id, 'life_months' => '60', 'salvage_base' => '0',
        ])->assertRedirect(route('assets.index'));
        $asset = Asset::firstOrFail();

        $this->assertSame('-12000.00', Ledger::balance($cash->id));
        $this->assertSame('12000.00', Ledger::balance(Account::system('fixed_assets')->id));

        // one month = 12,000 / 60 = 200; pressing twice changes nothing
        $this->post(route('assets.depreciate'), ['period' => '2026-01'])->assertSessionHas('success');
        $this->post(route('assets.depreciate'), ['period' => '2026-01'])->assertSessionHas('info');
        $this->post(route('assets.depreciate'), ['period' => '2026-02'])->assertSessionHas('success');
        $this->assertSame('400.00', Ledger::balance(Account::system('depreciation')->id));
        $this->assertSame('-400.00', Ledger::balance(Account::system('accumulated_depreciation')->id));

        // take February back
        $this->post(route('assets.undo'), ['period' => '2026-02'])->assertSessionHas('success');
        $this->assertSame('200.00', Ledger::balance(Account::system('depreciation')->id));
        $this->get(route('assets.index'))->assertOk()->assertSee('Filling machine')->assertSee('11,800.00');

        // sold for 11,000: it was worth 11,800, so 800 is lost
        $this->post(route('assets.dispose', ['id' => $asset->id]), ['date' => '2026-03-01', 'sold_for' => '11000', 'money_id' => $cash->id])->assertSessionHas('success');
        $this->assertSame('disposed', $asset->fresh()->status);
        $this->assertSame('0.00', Ledger::balance(Account::system('fixed_assets')->id));
        $this->assertSame('0.00', Ledger::balance(Account::system('accumulated_depreciation')->id));
        $this->assertSame('-1000.00', Ledger::balance($cash->id));
        $this->assertSame('1000.00', Ledger::balance(Account::system('depreciation')->id));
    }

    public function test_an_intangible_asset_we_already_had_is_amortized(): void
    {
        $this->rate('AFN', '70');
        $this->post(route('assets.store'), [
            'name' => 'AISA licence', 'class' => 'fixed', 'kind' => 'intangible', 'purchase_date' => '2026-01-01',
            'cost' => '70000', 'currency_id' => $this->currency('AFN')->id, 'life_months' => '10',
        ])->assertRedirect(route('assets.index'));

        $this->assertSame('1000.00', Ledger::balance(Account::system('intangible_assets')->id));
        $this->assertSame('-1000.00', Ledger::balance(Account::system('opening')->id));

        $this->post(route('assets.depreciate'), ['period' => '2026-01']);
        $this->assertSame('100.00', Ledger::balance(Account::system('depreciation')->id));
    }

    public function test_monthly_payroll_with_an_advance(): void
    {
        $this->rate('AFN', '70');
        $worker = $this->account('employee', 'Abdul Wahab', 'AFN', ['meta' => ['salary' => '8000', 'salary_tax' => '0', 'job_title' => 'Worker']]);
        $manager = $this->account('employee', 'General Manager', 'AFN', ['meta' => ['salary' => '12000', 'salary_tax' => '200']]);
        $cash = $this->cash('AFN');

        // an advance of 3,000 during the month
        $this->post(route('money.store', ['kind' => 'out']), ['group' => 'employee', 'date' => '2026-10-10', 'money_id' => $cash->id, 'other_id' => $worker->id, 'amount' => '3000']);

        $this->get(route('payroll.index'))->assertOk();
        $this->get(route('payroll.create', ['period' => '2026-10']))->assertOk()->assertSee('Abdul Wahab')->assertSee('8000');

        $this->post(route('payroll.store'), ['period' => '2026-10', 'date' => '2026-10-31', 'lines' => [
            ['employee_id' => $worker->id, 'include' => '1', 'salary' => '8000', 'bonus' => '500', 'deduction' => '0', 'tax' => '0'],
            ['employee_id' => $manager->id, 'include' => '1', 'salary' => '12000', 'tax' => '200'],
        ]])->assertRedirect();
        $payroll = Payroll::firstOrFail();

        $this->assertSame('-5500.00', Ledger::balance($worker->id));     // 8,500 earned, 3,000 already taken
        $this->assertSame('-11800.00', Ledger::balance($manager->id));   // 12,000 less 200 tax
        $this->assertSame('292.86', Ledger::balance(Account::system('salaries')->id));       // 20,500 AFN in USD
        $this->assertSame('-2.86', Ledger::balance(Account::system('tax_payable')->id));
        $this->get(route('payroll.show', ['id' => $payroll->id]))->assertOk()->assertSee('we owe');
        $this->get(route('statement', ['id' => $worker->id]))->assertOk();

        // the same month cannot be saved twice
        $this->post(route('payroll.store'), ['period' => '2026-10', 'date' => '2026-10-31', 'lines' => [['employee_id' => $worker->id, 'include' => '1', 'salary' => '8000']]])->assertSessionHasErrors('period');

        // taking it back frees the month
        $this->delete(route('payroll.destroy', ['id' => $payroll->id]))->assertRedirect(route('payroll.index'));
        $this->assertSame('3000.00', Ledger::balance($worker->id));
        $this->assertDatabaseCount('payrolls', 0);
    }

    public function test_reports_tell_the_same_story_as_the_books(): void
    {
        $this->rate('AFN', '70');
        $warehouse = Warehouse::create(['name' => 'Main warehouse']);
        $supplier = $this->account('supplier', 'Javed', 'USD');
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');
        $owner = $this->account('shareholder', 'Owner', 'USD', ['meta' => ['share_percent' => '100']]);
        $rent = $this->account('expense', 'Rent', 'USD');
        $mango = Item::create(['type' => 'product', 'name' => 'Mango KC9012', 'unit_id' => Unit::where('name', 'Can')->value('id')]);
        $cash = $this->cash('USD');

        $this->post(route('money.store', ['kind' => 'in']), ['group' => 'shareholder', 'date' => '2026-10-01', 'money_id' => $cash->id, 'other_id' => $owner->id, 'amount' => '5000']);
        $this->post(route('purchases.store'), ['type' => 'invoice', 'date' => '2026-10-02', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'received' => '1',
            'paid' => '1000', 'paid_from_id' => $cash->id, 'lines' => [['item_id' => $mango->id, 'qty' => '50', 'price' => '40']]]);
        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-10-10', 'customer_id' => $customer->id, 'warehouse_id' => $warehouse->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '10', 'price' => '4900']]]);       // 49,000 AFN = 700 USD, cost 400
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-15', 'money_id' => $cash->id, 'other_id' => $rent->id, 'amount' => '100']);

        $range = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.profit', $range))->assertOk()->assertSee('700.00')->assertSee('400.00')->assertSee('300.00')->assertSee('200.00 USD');
        $this->get(route('reports.balance', ['to' => '2026-10-31']))->assertOk()->assertSee('The books balance')->assertSee('5,200.00');
        $this->get(route('reports.trial', ['to' => '2026-10-31']))->assertOk()->assertSee('Haji Sharif');
        $this->get(route('reports.cash', $range))->assertOk()->assertSee('3,900.00');
        $this->get(route('reports.balances', ['to' => '2026-10-31']))->assertOk()->assertSee('49,000.00 AFN')->assertSee('1,000.00 USD');
        $this->get(route('reports.sales', $range))->assertOk()->assertSee('Mango KC9012')->assertSee('300.00');
        $this->get(route('reports.purchases', $range))->assertOk()->assertSee('2,000.00');

        // every report has a printable paper of its own: company heading, the same figures, no menu
        $this->get(route('reports.profit', $range))->assertSee('print=1', false);
        $this->get(route('reports.profit', $range + ['print' => 1]))->assertOk()
            ->assertSee('paper-head', false)->assertSee('Profit and loss')->assertSee('700.00')->assertSee('200.00 USD')->assertSee('Prepared by')
            ->assertDontSee('app-sidebar', false)->assertDontSee('name="from"', false);
        foreach (['reports.balance', 'reports.trial', 'reports.cash', 'reports.balances', 'reports.sales', 'reports.purchases'] as $report) {
            $this->get(route($report, $range + ['print' => 1]))->assertOk()->assertSee('paper-head', false)->assertSee('Approved by')->assertDontSee('app-sidebar', false);
        }
        $this->get(route('reports.parties', ['group' => 'customers', 'print' => 1]))->assertOk()->assertSee('paper-head', false)->assertSee('Haji Sharif');

        // stock on hand, batches, the stock card, the day book and the production list print the same way
        $this->get(route('stock.index', ['from' => 'reports']))->assertOk()->assertSee('print=1', false);
        $this->get(route('stock.index', ['from' => 'reports', 'print' => 1]))->assertOk()
            ->assertSee('paper-head', false)->assertSee('Stock on hand')->assertSee('Mango KC9012')->assertSee('All warehouses')->assertDontSee('app-sidebar', false);
        $this->get(route('stock.batches', ['print' => 1]))->assertOk()->assertSee('paper-head', false);
        $this->get(route('stock.card', ['id' => $mango->id, 'from' => '2026-10-01', 'to' => '2026-10-31', 'print' => 1]))->assertOk()->assertSee('paper-head', false)->assertSee('Stock card');
        $this->get(route('journal.index', ['print' => 1]))->assertOk()->assertSee('paper-head', false);
        $this->get(route('production.index', ['print' => 1]))->assertOk()->assertSee('paper-head', false);
        $this->get(route('purchases.show', ['id' => \App\Models\Purchase::firstOrFail()->id, 'print' => 1]))->assertOk()->assertSee('paper-head', false);

        // the dashboard shows the same numbers
        $this->get(route('dashboard'))->assertOk()->assertSee('3,900.00 USD')->assertSee('1,600.00');
        $this->get(route('dashboard'))->assertOk()->assertSee('Sales in the last 30 days')->assertSee('Sales and purchases, last 6 months')->assertSee('Best sellers this month')->assertSee('chart-bar s2', false);
    }

    public function test_a_user_only_sees_the_dashboard_parts_he_may_see(): void
    {
        $this->userWith(['sales.view']);

        $this->get(route('dashboard'))->assertOk()->assertSee('Sales today')->assertDontSee('Money we have')->assertDontSee('Stock is worth');
        $this->get(route('dashboard'))->assertSee('Sales in the last 30 days')->assertDontSee('chart-bar s2', false);
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('assets.index'))->assertForbidden();
        $this->get(route('payroll.index'))->assertForbidden();
    }
}
