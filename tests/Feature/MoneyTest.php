<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    use RefreshDatabase;

    private function assertBooksBalance(): void
    {
        $sums = JournalLine::where('is_void', false)->selectRaw('SUM(base_debit) as d, SUM(base_credit) as c')->toBase()->first();
        $this->assertSame(number_format((float) $sums->d, 2, '.', ''), number_format((float) $sums->c, 2, '.', ''), 'Debits and credits differ.');
    }

    public function test_a_customer_with_an_opening_balance(): void
    {
        $this->admin();
        $this->rate('AFN', '70');

        $this->post(route('accounts.store', ['type' => 'customer']), [
            'name' => 'Haji Sharif', 'currency_id' => $this->currency('AFN')->id, 'phone' => '0700000000', 'address' => 'Jalalabad',
            'is_active' => '1', 'opening_amount' => '7000', 'opening_side' => 'debit',
        ])->assertRedirect(route('accounts.index', ['type' => 'customer']));

        $customer = Account::where('name', 'Haji Sharif')->firstOrFail();
        $this->assertSame('7000.00', Ledger::balance($customer->id));
        $this->assertSame('100.00', Ledger::baseBalance($customer->id));
        $this->assertBooksBalance();

        $this->get(route('accounts.index', ['type' => 'customer']))->assertOk()->assertSee('Haji Sharif')->assertSee('owes us');
        // balances are coloured: green when they owe us
        $this->get(route('accounts.index', ['type' => 'customer']))->assertSee('bal bal-due', false)->assertSee('7,000.00 AFN');
        $this->assertSame('owe', $customer->balanceTone('-5'));
        $this->assertSame('zero', $customer->balanceTone('0'));
        $this->get(route('statement', ['id' => $customer->id]))->assertOk()->assertSee('7,000.00');
        // the statement offers quick periods and names the kind of each movement
        $this->get(route('statement', ['id' => $customer->id, 'from' => '2000-01-01', 'to' => today_iso()]))
            ->assertOk()->assertSee('This month')->assertSee('All time')->assertSee('Opening balance')->assertSee('Jalalabad')->assertSee('Prepared by');
        $this->get(route('statement', ['id' => $customer->id, 'from' => '2000-01-01', 'to' => '2000-01-31']))
            ->assertOk()->assertSee('Nothing happened in these dates');
        $this->get(route('chart'))->assertOk()->assertSee('Haji Sharif');

        // an account with transactions cannot be deleted
        $this->delete(route('accounts.destroy', ['type' => 'customer', 'id' => $customer->id]))->assertSessionHas('danger');
        $this->assertNotNull($customer->fresh());
    }

    public function test_nothing_is_saved_when_the_exchange_rate_is_missing(): void
    {
        $this->admin();

        $this->post(route('accounts.store', ['type' => 'customer']), [
            'name' => 'No Rate', 'currency_id' => $this->currency('AFN')->id, 'is_active' => '1', 'opening_amount' => '500', 'opening_side' => 'debit',
        ])->assertSessionHas('danger');

        $this->assertDatabaseMissing('accounts', ['name' => 'No Rate']);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_money_in_money_out_and_expense(): void
    {
        $this->admin();
        $customer = $this->account('customer', 'Zaki', 'USD');
        $supplier = $this->account('supplier', 'Javed', 'USD');
        $rent = $this->account('expense', 'Rent', 'USD');
        $cash = $this->cash('USD');

        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $cash->id, 'other_id' => $customer->id, 'amount' => '2200'])->assertRedirect(route('money.index'));
        $this->post(route('money.store', ['kind' => 'out']), ['date' => '2026-10-27', 'money_id' => $cash->id, 'other_id' => $supplier->id, 'amount' => '500'])->assertRedirect(route('money.index'));
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-27', 'money_id' => $cash->id, 'other_id' => $rent->id, 'amount' => '300', 'description' => 'October rent'])->assertRedirect(route('money.index'));

        $this->assertSame('1400.00', Ledger::balance($cash->id));
        $this->assertSame('-2200.00', Ledger::balance($customer->id)); // we hold the customer's money in advance
        $this->assertSame('500.00', Ledger::balance($supplier->id));   // the supplier holds our advance
        $this->assertSame('300.00', Ledger::balance($rent->id));
        $this->assertBooksBalance();

        $this->get(route('money.index'))->assertOk()->assertSee('October rent');
        $this->get(route('journal.index', ['from' => '2026-10-27', 'to' => '2026-10-27']))->assertOk()->assertSee('October rent');

        // an expense must go to an expense category
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-27', 'money_id' => $cash->id, 'other_id' => $customer->id, 'amount' => '10'])->assertSessionHas('danger');
    }

    public function test_money_between_two_currencies(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $customer = $this->account('customer', 'Zaki', 'USD');

        // 7,000 AFN received from a customer whose account is in USD: 100 USD comes off his account
        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $this->cash('AFN')->id, 'other_id' => $customer->id, 'amount' => '7000']);
        $this->assertSame('7000.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('-100.00', Ledger::balance($customer->id));

        // exchange 3,500 AFN for 49 USD at the sarafi's own rate
        $this->post(route('money.store', ['kind' => 'transfer']), ['date' => '2026-10-27', 'money_id' => $this->cash('AFN')->id, 'other_id' => $this->cash('USD')->id, 'amount' => '3500', 'other_amount' => '49']);
        $this->assertSame('3500.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('49.00', Ledger::balance($this->cash('USD')->id));
        $this->assertBooksBalance();
    }

    public function test_a_money_entry_can_be_cancelled(): void
    {
        $this->admin();
        $customer = $this->account('customer', 'Zaki', 'USD');
        $cash = $this->cash('USD');

        $this->post(route('money.store', ['kind' => 'in']), ['date' => '2026-10-27', 'money_id' => $cash->id, 'other_id' => $customer->id, 'amount' => '100']);
        $entry = JournalEntry::firstOrFail();

        $this->delete(route('money.destroy', ['id' => $entry->id]))->assertRedirect();

        $this->assertTrue($entry->fresh()->isVoid());
        $this->assertSame('0.00', Ledger::balance($cash->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));
    }

    public function test_capital_loan_and_profit_sharing(): void
    {
        $this->admin();
        $cash = $this->cash('USD');
        $a = $this->account('shareholder', 'Partner A', 'USD', ['meta' => ['share_percent' => '60']]);
        $b = $this->account('shareholder', 'Partner B', 'USD', ['meta' => ['share_percent' => '40']]);
        $loan = $this->account('liability', 'Bank loan', 'USD');

        $this->post(route('money.store', ['kind' => 'in']), ['group' => 'shareholder', 'date' => '2026-01-01', 'money_id' => $cash->id, 'other_id' => $a->id, 'amount' => '10000']);
        $this->post(route('money.store', ['kind' => 'in']), ['group' => 'liability', 'date' => '2026-01-02', 'money_id' => $cash->id, 'other_id' => $loan->id, 'amount' => '5000']);
        $this->post(route('money.profit.store'), ['date' => '2026-06-30', 'amount' => '1000.01'])->assertRedirect(route('money.index'));
        $this->post(route('money.store', ['kind' => 'out']), ['group' => 'shareholder', 'date' => '2026-07-01', 'money_id' => $cash->id, 'other_id' => $b->id, 'amount' => '400']);

        $this->assertSame('-10600.01', Ledger::balance($a->id));  // capital 10,000 + 60% of the profit
        $this->assertSame('0.00', Ledger::balance($b->id));       // 40% of the profit, taken out
        $this->assertSame('-5000.00', Ledger::balance($loan->id));
        $this->assertSame('14600.00', Ledger::balance($cash->id));
        $this->assertBooksBalance();
    }

    public function test_a_hand_written_journal_entry_must_balance(): void
    {
        $this->admin();
        $rent = $this->account('expense', 'Rent', 'USD');
        $cash = $this->cash('USD');

        $this->post(route('journal.store'), ['date' => '2026-10-27', 'description' => 'Wrong', 'lines' => [
            ['account_id' => $rent->id, 'debit' => '100'], ['account_id' => $cash->id, 'credit' => '90'],
        ]])->assertSessionHas('danger');
        $this->assertDatabaseCount('journal_entries', 0);

        $this->post(route('journal.store'), ['date' => '2026-10-27', 'description' => 'Right', 'lines' => [
            ['account_id' => $rent->id, 'debit' => '100'], ['account_id' => $cash->id, 'credit' => '100'],
        ]])->assertRedirect();
        $this->assertSame('-100.00', Ledger::balance($cash->id));
    }
}
