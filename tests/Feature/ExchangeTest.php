<?php

namespace Tests\Feature;

use App\Models\CurrencyExchange;
use App\Models\JournalLine;
use App\Services\ExchangeService;
use App\Services\Ledger;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeTest extends TestCase
{
    use RefreshDatabase;

    private function holder(string $name): string
    {
        return ExchangeService::holders()->firstOrFail(fn (array $holder) => $holder['name'] === $name)['key'];
    }

    private function exchange(array $data)
    {
        return $this->post(route('exchange.store'), $data + ['date' => '2026-10-27', 'holder' => $this->holder('Cash')]);
    }

    private function assertBooksBalance(): void
    {
        $sums = JournalLine::where('is_void', false)->selectRaw('SUM(base_debit) as d, SUM(base_credit) as c')->toBase()->first();
        $this->assertSame(number_format((float) $sums->d, 2, '.', ''), number_format((float) $sums->c, 2, '.', ''), 'Debits and credits differ.');
    }

    public function test_the_rate_of_the_rates_table_is_used_when_none_is_typed(): void
    {
        $this->admin();
        $this->rate('AFN', '70');

        $this->get(route('exchange.index'))->assertOk()->assertSee('Seller currency')->assertSee('Operating type')->assertSee('Buyer currency')->assertSee('Cash');
        $rates = $this->getJson(route('exchange.rates', ['date' => '2026-10-27']))->assertOk()->json();
        $this->assertSame('70.00', Num::money($rates[$this->currency('AFN')->id]));
        $this->assertNull($rates[$this->currency('PKR')->id]);

        $this->exchange(['sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '100'])
            ->assertRedirect(route('exchange.index'));

        $exchange = CurrencyExchange::firstOrFail();
        $this->assertSame('multiply', $exchange->operation);
        $this->assertSame('70.000000', Num::round($exchange->rate, 6));
        $this->assertSame('7000.00', Num::money($exchange->buy_amount));
        $this->assertSame('-100.00', Ledger::balance($this->cash('USD')->id));
        $this->assertSame('7000.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertBooksBalance();

        $this->get(route('exchange.index'))->assertOk()->assertSee($exchange->number)->assertSee('7,000.00 AFN');
        $this->get(route('statement', ['id' => $this->cash('AFN')->id, 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->assertSee('Currency exchange');
    }

    public function test_a_manual_rate_can_divide(): void
    {
        $this->admin();
        $this->rate('AFN', '70');

        // 7,100 AFN sold at 71 afghani a dollar buys 100 USD
        $this->exchange(['sell_currency_id' => $this->currency('AFN')->id, 'buy_currency_id' => $this->currency('USD')->id, 'sell_amount' => '7,100', 'rate' => '71', 'operation' => 'divide', 'buy_amount' => '100.00'])
            ->assertRedirect(route('exchange.index'));

        $this->assertSame('-7100.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('100.00', Ledger::balance($this->cash('USD')->id));
        $this->assertSame('-100.00', Ledger::baseBalance($this->cash('AFN')->id));
        $this->assertBooksBalance();
    }

    public function test_two_currencies_that_are_not_the_main_one(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $this->rate('PKR', '280');

        $this->exchange(['sell_currency_id' => $this->currency('AFN')->id, 'buy_currency_id' => $this->currency('PKR')->id, 'sell_amount' => '1000'])->assertRedirect();
        $exchange = CurrencyExchange::firstOrFail();
        $this->assertSame(['multiply', '4.000000', '4000.00'], [$exchange->operation, Num::round($exchange->rate, 6), Num::money($exchange->buy_amount)]);

        $this->exchange(['sell_currency_id' => $this->currency('PKR')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '4000'])->assertRedirect();
        $this->assertSame('0.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('0.00', Ledger::balance($this->cash('PKR')->id));
        $this->assertBooksBalance();
    }

    public function test_a_sarafi_with_two_currencies_and_cancelling(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $usd = $this->account('sarafi', 'Ahmad Sarafi', 'USD');
        $afn = $this->account('sarafi', 'Ahmad Sarafi', 'AFN');

        $this->exchange(['holder' => $this->holder('Ahmad Sarafi'), 'sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '200', 'rate' => '70.5', 'operation' => 'multiply', 'description' => 'Bazaar rate'])
            ->assertRedirect();
        $this->assertSame('-200.00', Ledger::balance($usd->id));
        $this->assertSame('14100.00', Ledger::balance($afn->id));

        $exchange = CurrencyExchange::firstOrFail();
        $this->delete(route('exchange.destroy', ['id' => $exchange->id]))->assertRedirect();
        $this->assertTrue($exchange->fresh()->isCancelled());
        $this->assertSame('0.00', Ledger::balance($usd->id));
        $this->assertSame('0.00', Ledger::balance($afn->id));
    }

    public function test_nothing_is_saved_when_the_numbers_do_not_agree_or_a_rate_is_missing(): void
    {
        $this->admin();

        // no rate saved for AFN and none typed
        $this->exchange(['sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '100'])->assertSessionHas('danger');

        $this->rate('AFN', '70');
        // 100 USD at 70 is 7,000 AFN, not 9,000
        $this->exchange(['sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '100', 'rate' => '70', 'operation' => 'multiply', 'buy_amount' => '9000'])->assertSessionHas('danger');
        // the same currency twice
        $this->exchange(['sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('USD')->id, 'sell_amount' => '100'])->assertSessionHasErrors('buy_currency_id');

        $this->assertSame(0, CurrencyExchange::count());
        $this->assertSame(0, JournalLine::count());
    }

    public function test_a_user_who_may_only_look_cannot_exchange(): void
    {
        $this->userWith(['money.view']);
        $this->rate('AFN', '70');

        $this->get(route('exchange.index'))->assertOk()->assertDontSee('Save exchange');
        $this->exchange(['sell_currency_id' => $this->currency('USD')->id, 'buy_currency_id' => $this->currency('AFN')->id, 'sell_amount' => '100'])->assertForbidden();
    }
}
