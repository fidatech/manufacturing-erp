<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Changes asked by the client after the first look at the system (November 2026). */
class ClientChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_can_be_filtered_by_category_currency_and_dates(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $rent = $this->account('expense', 'Rent', 'USD');
        $fuel = $this->account('expense', 'Fuel', 'USD');
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-05', 'money_id' => $this->cash('USD')->id, 'other_id' => $rent->id, 'amount' => '300', 'description' => 'October rent']);
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-20', 'money_id' => $this->cash('AFN')->id, 'other_id' => $fuel->id, 'amount' => '7000', 'description' => 'Generator diesel']);
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-11-02', 'money_id' => $this->cash('USD')->id, 'other_id' => $rent->id, 'amount' => '300', 'description' => 'November rent']);

        $list = fn (array $query) => $this->get(route('expenses.index', $query + ['from' => '2026-10-01', 'to' => '2026-11-30']))->assertOk();
        $list([])->assertSee('Expenses')->assertSee('By category')->assertSee('New expense')->assertSee('All categories')->assertSee('All currencies')->assertSee('October rent')->assertSee('Generator diesel')->assertSee('700.00 USD');
        $list(['category' => $rent->id])->assertSee('October rent')->assertSee('November rent')->assertDontSee('Generator diesel')->assertSee('600.00 USD');
        $list(['currency' => $this->currency('AFN')->id])->assertSee('Generator diesel')->assertDontSee('October rent')->assertSee('100.00 USD');
        $list(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertSee('October rent')->assertSee('Generator diesel')->assertDontSee('November rent');
        $this->get(route('expenses.index', ['from' => '2026-10-01', 'to' => '2026-11-30', 'export' => 'xlsx']))->assertOk();

        // the money page no longer mixes expenses in; its old link opens the expenses page
        $this->get(route('money.index'))->assertOk()->assertDontSee('October rent')->assertDontSee('Expense category');
        $this->get(route('money.index', ['type' => 'expense']))->assertRedirect(route('expenses.index'));
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-11-03', 'money_id' => $this->cash('USD')->id, 'other_id' => $rent->id, 'amount' => '5', 'description' => 'Lock'])->assertRedirect(route('expenses.index'));
    }

    public function test_employee_documents_are_kept_inside_human_resources(): void
    {
        Storage::fake('local');
        $this->userWith(['hr.view', 'hr.manage']);
        $wahab = $this->account('employee', 'Abdul Wahab', 'AFN');
        $khalid = $this->account('employee', 'Khalid Khan', 'AFN');

        $this->get(route('hr.documents.index'))->assertOk()->assertSee('Employee Documents')->assertSee('Abdul Wahab');
        $this->post(route('hr.documents.store'), ['employee_id' => $wahab->id, 'title' => 'Tazkira copy', 'file' => UploadedFile::fake()->create('tazkira.pdf', 120, 'application/pdf')])
            ->assertRedirect(route('hr.documents.index', ['employee' => $wahab->id]));
        $this->post(route('hr.documents.store'), ['employee_id' => $khalid->id, 'title' => 'Contract', 'file' => UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf')]);

        $file = Attachment::where('title', 'Tazkira copy')->firstOrFail();
        $this->assertSame(['Account', $wahab->id], [$file->subject_type, (int) $file->subject_id]);
        $this->get(route('hr.documents.index', ['employee' => $wahab->id]))->assertSee('Tazkira copy')->assertDontSee('>Contract<', false);
        $this->get(route('hr.documents.show', ['id' => $file->id]))->assertOk();

        // a file of the company that is not an employee's is out of reach here
        $other = Attachment::create(['title' => 'Factory licence', 'path' => 'documents/x.pdf', 'original_name' => 'x.pdf', 'size' => 1]);
        $this->get(route('hr.documents.show', ['id' => $other->id]))->assertNotFound();
        $this->post(route('hr.documents.store'), ['employee_id' => $this->cash('USD')->id, 'title' => 'x', 'file' => UploadedFile::fake()->create('x.pdf', 1)])->assertNotFound();

        $this->delete(route('hr.documents.destroy', ['id' => $file->id]))->assertRedirect();
        $this->assertNull(Attachment::find($file->id));

        // looking only: no upload form
        $this->userWith(['hr.view']);
        $this->get(route('hr.documents.index'))->assertOk()->assertSee('Contract')->assertDontSee('Save file');
    }

    public function test_money_in_can_take_several_currencies_with_their_own_rates(): void
    {
        $this->admin();
        $this->rate('AFN', '70', today_iso());
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');

        $this->get(route('money.create', ['kind' => 'in']))->assertOk()->assertSee('data-money-lines', false)->assertSee('Add another currency')
            ->assertSee('Balance after')->assertSee('data-balance=', false)->assertSee('data-pos="owes us"', false);

        // 100 dollars at 71 afghani each, and 3,000 afghani in cash: 10,100 afghani off his account
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '100', 'rate' => '71', 'operation' => 'multiply'],
            ['money_id' => $this->cash('AFN')->id, 'amount' => '3,000'],
            ['money_id' => '', 'amount' => ''],
        ]])->assertRedirect(route('money.index'));

        $this->assertSame('-10100.00', \App\Services\Ledger::balance($customer->id));
        $this->assertSame('100.00', \App\Services\Ledger::balance($this->cash('USD')->id));
        $this->assertSame('3000.00', \App\Services\Ledger::balance($this->cash('AFN')->id));
        $entry = \App\Models\JournalEntry::latest('id')->firstOrFail();
        $this->assertSame(3, $entry->lines()->count());
        $this->assertStringContainsString('100.00 USD + 3,000.00 AFN', $entry->description);

        // money out in two currencies at the rate of the day, the amount in his currency typed by hand on one line
        $this->post(route('money.store', ['kind' => 'out']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '10'],
            ['money_id' => $this->cash('USD')->id, 'amount' => '5', 'other_amount' => '360'],
        ]])->assertRedirect();
        $this->assertSame('-9040.00', \App\Services\Ledger::balance($customer->id));   // 10 x 70 + 360 = 1,060 back to him

        // a line without an account, or nothing at all, is refused and nothing is saved
        $count = \App\Models\JournalEntry::count();
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => '', 'amount' => '50']]])->assertSessionHas('danger');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => '', 'amount' => '']]])->assertSessionHas('danger');
        $this->assertSame($count, \App\Models\JournalEntry::count());
    }

    public function test_customer_and_supplier_paid_in_other_currencies_are_converted_to_their_own(): void
    {
        $this->admin();
        $this->rate('AFN', '70', today_iso());

        // AFN customer pays 100 USD: the dollars go to the USD cash box, his AFN account goes down by 7,000
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '100'],
        ]])->assertRedirect();
        $this->assertSame('-7000.00', \App\Services\Ledger::balance($customer->id));
        $this->assertSame('100.00', \App\Services\Ledger::balance($this->cash('USD')->id));

        // USD supplier, no dollars in cash: 14,000 AFN paid, divided by 70 = 200 USD off what we owe him
        $supplier = $this->account('supplier', 'Karachi Packing Co', 'USD');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => $this->cash('AFN')->id, 'amount' => '20000']]]);
        $this->post(route('money.store', ['kind' => 'out']), ['date' => today_iso(), 'other_id' => $supplier->id, 'lines' => [
            ['money_id' => $this->cash('AFN')->id, 'amount' => '14,000', 'rate' => '70', 'operation' => 'divide'],
        ]])->assertRedirect();
        $this->assertSame('200.00', \App\Services\Ledger::balance($supplier->id));
        $this->assertSame('6000.00', \App\Services\Ledger::balance($this->cash('AFN')->id));
    }

    public function test_supplier_advance_prints_a_tt_receipt_with_its_number(): void
    {
        $this->admin();
        $supplier = $this->account('supplier', 'Karachi Packing Co', 'USD');
        $this->post(route('money.store', ['kind' => 'out']), ['date' => today_iso(), 'other_id' => $supplier->id, 'reference' => 'TT-88412', 'description' => 'Advance for cartons', 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '500'],
        ]])->assertRedirect();
        $entry = \App\Models\JournalEntry::latest('id')->firstOrFail();
        $this->assertSame('TT-88412', $entry->reference);

        $this->get(route('money.index'))->assertSee('TT-88412')->assertSee(route('money.receipt', ['id' => $entry->id]), false);
        $this->get(route('money.receipt', ['id' => $entry->id]))->assertOk()
            ->assertSee('TT Receipt')->assertSee('TT number')->assertSee('TT-88412')->assertSee('Karachi Packing Co')->assertSee('500.00 USD');

        // money in from a customer is a normal receipt
        $customer = $this->account('customer', 'Haji Sharif', 'USD');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => $this->cash('USD')->id, 'amount' => '40']]]);
        $this->get(route('money.receipt', ['id' => \App\Models\JournalEntry::latest('id')->value('id')]))->assertOk()->assertSee('Money Receipt')->assertDontSee('TT Receipt');
    }

    public function test_dead_cash_lists_customers_who_have_not_paid_for_long(): void
    {
        $this->admin();
        $this->rate('AFN', '70', '2026-01-01');
        $old = $this->account('customer', 'Haji Sharif', 'USD', ['phone' => '0700111222']);
        $recent = $this->account('customer', 'Gul Agha', 'USD');
        $never = $this->account('customer', 'Wali Store', 'AFN');
        $sales = $this->account('income', 'Juice sales', 'USD');
        $salesAfn = $this->account('income', 'Juice sales AFN', 'AFN');
        $cash = $this->cash('USD');
        $day = fn (int $ago) => now()->subDays($ago)->toDateString();
        \App\Services\Ledger::post('sale', $day(400), 'Sale', [['account' => $old, 'debit' => '500'], ['account' => $sales, 'credit' => '500']]);
        \App\Services\Ledger::post('receipt', $day(200), 'Paid a little', [['account' => $cash, 'debit' => '100'], ['account' => $old, 'credit' => '100']]);
        \App\Services\Ledger::post('sale', $day(20), 'Sale', [['account' => $recent, 'debit' => '80'], ['account' => $sales, 'credit' => '80']]);
        \App\Services\Ledger::post('sale', $day(100), 'Sale', [['account' => $never, 'debit' => '7000', 'base' => '100'], ['account' => $salesAfn, 'credit' => '7000', 'base' => '100']]);

        // 90 days and more: Haji Sharif (last paid 200 days ago) and Wali Store (never paid, owes for 100 days)
        $page = $this->get(route('dead-cash.index'))->assertOk()->assertSee('Customers who owe us and have not paid for a long time.');
        $page->assertSee('Haji Sharif')->assertSee('400.00 USD')->assertSee('0700111222')->assertSee('200 days')
            ->assertSee('Wali Store')->assertSee('Never paid')->assertSee('100 days')->assertDontSee('Gul Agha');
        $this->get(route('dead-cash.index', ['days' => 180]))->assertSee('Haji Sharif')->assertDontSee('Wali Store');
        $this->get(route('dead-cash.index', ['days' => 30, 'currency' => $this->currency('AFN')->id]))->assertSee('Wali Store')->assertDontSee('Haji Sharif');
        $this->get(route('dead-cash.index', ['export' => 'xlsx']))->assertOk();

        // a debt that will never come back can be written off, and the write-off cancelled
        $this->post(route('dead-cash.store'), ['date' => today_iso(), 'account_id' => $old->id, 'amount' => '400', 'reason' => 'Left the country'])->assertRedirect(route('dead-cash.index'));
        $this->assertSame('0.00', \App\Services\Ledger::balance($old->id));
        $this->get(route('dead-cash.index'))->assertDontSee('400.00 USD</td>', false)->assertSee('Written off')->assertSee('Left the country');
        $this->post(route('dead-cash.store'), ['date' => today_iso(), 'account_id' => $never->id, 'amount' => '9000', 'reason' => 'x'])->assertSessionHas('danger');
        $this->post(route('dead-cash.store'), ['date' => today_iso(), 'account_id' => $cash->id, 'amount' => '10', 'reason' => 'x'])->assertSessionHas('danger');
        $entry = \App\Models\JournalEntry::where('type', 'dead_cash')->firstOrFail();
        $this->delete(route('dead-cash.destroy', ['id' => $entry->id]))->assertRedirect();
        $this->assertSame('400.00', \App\Services\Ledger::balance($old->id));
    }

    public function test_last_months_profit_is_shared_by_itself_once(): void
    {
        $this->admin();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 01:00', 'Asia/Kabul'));
        $a = $this->account('shareholder', 'Partner A', 'USD', ['meta' => ['share_percent' => '60']]);
        $b = $this->account('shareholder', 'Partner B', 'USD', ['meta' => ['share_percent' => '40']]);
        $cash = $this->cash('USD');
        $income = $this->account('income', 'Juice sales', 'USD');
        $rent = $this->account('expense', 'Rent', 'USD');
        \App\Services\Ledger::post('receipt', '2026-10-10', 'October sales', [['account' => $cash, 'debit' => '1000'], ['account' => $income, 'credit' => '1000']]);
        \App\Services\Ledger::post('expense', '2026-10-20', 'October rent', [['account' => $rent, 'debit' => '200'], ['account' => $cash, 'credit' => '200']]);
        \App\Services\Ledger::post('receipt', '2026-11-01', 'November sales', [['account' => $cash, 'debit' => '500'], ['account' => $income, 'credit' => '500']]);

        $this->artisan('profit:distribute')->assertSuccessful()->expectsOutputToContain('was shared');
        $this->assertSame('-480.00', \App\Services\Ledger::balance($a->id));   // 60% of 800, owed to him
        $this->assertSame('-320.00', \App\Services\Ledger::balance($b->id));
        $entry = \App\Models\JournalEntry::where('type', 'profit')->firstOrFail();
        $this->assertSame(['2026-10-31', 'PROFIT-2026-10'], [$entry->date->format('Y-m-d'), $entry->reference]);

        // a second run, or the button, never shares the month again
        $this->artisan('profit:distribute')->assertSuccessful()->expectsOutputToContain('already shared');
        $this->post(route('money.profit.month'), ['month' => '2026-10'])->assertSessionHas('warning');
        $this->assertSame(1, \App\Models\JournalEntry::where('type', 'profit')->count());
        $this->get(route('money.profit'))->assertOk()->assertSee('Monthly profit')->assertSee($entry->number);

        // the month that is still running cannot be shared; a month with a loss shares nothing
        $this->post(route('money.profit.month'), ['month' => '2026-11'])->assertSessionHasErrors('month');
        $this->artisan('profit:distribute', ['--month' => '2026-09'])->assertSuccessful()->expectsOutputToContain('Nothing to share');

        // the scheduler runs it on the 1st of the month
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'profit:distribute'));
        $this->assertNotNull($event);
        $this->assertSame('0 1 1 * *', $event->expression);
    }

    public function test_enterprise_transactions_report_by_module(): void
    {
        $this->admin();
        $cash = $this->cash('USD');
        $customer = $this->account('customer', 'Haji Sharif', 'USD');
        $rent = $this->account('expense', 'Rent', 'USD');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'description' => 'Paid his bill', 'lines' => [['money_id' => $cash->id, 'amount' => '300']]]);
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => today_iso(), 'money_id' => $cash->id, 'other_id' => $rent->id, 'amount' => '120', 'description' => 'Office rent']);
        \App\Services\Ledger::post('journal', today_iso(), 'Correction by the accountant', [['account' => $rent, 'debit' => '5'], ['account' => $cash, 'credit' => '5']]);

        $this->get(route('reports.index'))->assertSee('Enterprise transactions');
        $this->get(route('reports.transactions'))->assertOk()->assertSee('Money')->assertSee('Accounting')
            ->assertSee('Paid his bill')->assertSee('Office rent')->assertSee('Correction by the accountant')->assertSee($rent->code);
        $this->get(route('reports.transactions', ['module' => 'accounting']))->assertSee('Correction by the accountant')->assertDontSee('Office rent');
        $this->get(route('reports.transactions', ['account' => $customer->id]))->assertSee('Paid his bill')->assertDontSee('Office rent');
        $this->get(route('reports.transactions', ['export' => 'xlsx']))->assertOk();
    }

    public function test_materials_check_before_a_batch(): void
    {
        $this->admin();
        $warehouse = \App\Models\Warehouse::create(['name' => 'Raw store']);
        $kg = \App\Models\Unit::where('name', 'Kilogram')->value('id');
        $sugar = \App\Models\Item::create(['type' => 'raw', 'name' => 'Sugar', 'unit_id' => $kg]);
        $acid = \App\Models\Item::create(['type' => 'raw', 'name' => 'Citric Acid', 'unit_id' => $kg]);
        $juice = \App\Models\Item::create(['type' => 'product', 'name' => 'Mango Juice', 'unit_id' => $kg]);
        $recipe = \App\Models\Recipe::create(['item_id' => $juice->id, 'name' => 'Mango Juice']);
        $recipe->lines()->createMany([['item_id' => $sugar->id, 'qty' => '2'], ['item_id' => $acid->id, 'qty' => '0.5']]);
        \App\Services\Stock::receive($sugar->id, $warehouse->id, '100', '1', today_iso(), 'opening', null);
        \App\Services\Stock::receive($acid->id, $warehouse->id, '10', '4', today_iso(), 'opening', null);

        // 30 units need 60 kg sugar (have 100) and 15 kg acid (have 10): acid is short 5 kg = 20 USD; stock is enough for 20
        $page = $this->get(route('production.materials', ['recipe' => $recipe->id, 'qty' => '30', 'warehouse' => $warehouse->id]))->assertOk();
        $page->assertSee('Materials Check')->assertSee('Sugar')->assertSee('Citric Acid')->assertSee('Short')->assertSee('1 / 2')->assertSee('20.00 USD');
        $this->assertStringContainsString('>20<', str_replace([' ', "\n"], '', $page->getContent()));
        $this->get(route('production.materials', ['recipe' => $recipe->id, 'qty' => '30', 'warehouse' => $warehouse->id, 'export' => 'xlsx']))->assertOk();
        $this->get(route('production.materials', ['recipe' => $recipe->id, 'qty' => '30', 'print' => 1]))->assertOk()->assertSee('Mango Juice');
        $this->get(route('production.create', ['recipe' => $recipe->id, 'qty' => '30', 'warehouse' => $warehouse->id]))->assertOk()->assertSee('Materials PDF')->assertSee('Materials Excel');
    }
}
