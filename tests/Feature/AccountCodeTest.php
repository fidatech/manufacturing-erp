<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_gets_a_chart_of_accounts_code(): void
    {
        $this->admin();
        $afn = $this->currency('AFN')->id;
        $usd = $this->currency('USD')->id;

        // the accounts of the system have fixed codes
        $this->assertSame('4000', Account::system('sales')->code);
        $this->assertSame('1310', Account::system('inventory')->code);
        $this->assertSame('1100-001-USD', Account::where('name', 'Cash USD')->value('code'));

        // one customer in two currencies: one number, the currency at the end
        $this->post(route('accounts.store', ['type' => 'customer']), ['name' => 'Haji Sharif', 'is_active' => '1', 'currencies' => [$afn => ['on' => '1'], $usd => ['on' => '1']]])->assertRedirect();
        $this->post(route('accounts.store', ['type' => 'customer']), ['name' => 'Junaid', 'is_active' => '1', 'currencies' => [$afn => ['on' => '1']]])->assertRedirect();
        $this->assertSame('1200-001-AFN', Account::where('name', 'Haji Sharif')->where('currency_id', $afn)->value('code'));
        $this->assertSame('1200-001-USD', Account::where('name', 'Haji Sharif')->where('currency_id', $usd)->value('code'));
        $this->assertSame('1200-002-AFN', Account::where('name', 'Junaid')->value('code'));

        // a currency added later to the same customer keeps his number
        $this->post(route('accounts.store', ['type' => 'customer']), ['name' => 'Junaid', 'is_active' => '1', 'currencies' => [$usd => ['on' => '1']]])->assertRedirect();
        $this->assertSame('1200-002-USD', Account::where('name', 'Junaid')->where('currency_id', $usd)->value('code'));

        // suppliers and expense categories have numbers of their own
        $supplier = $this->account('supplier', 'Zahir Plastic', 'AFN');
        $this->assertSame('2100-001-AFN', $supplier->code);
        $this->assertSame('5100-001', $this->account('expense', 'Rent', 'USD')->code);

        // the codes are shown and can be searched
        $this->get(route('chart'))->assertOk()->assertSee('1200-001-AFN')->assertSee('4000');
        $this->get(route('accounts.index', ['type' => 'customer', 'q' => '1200-002']))->assertOk()->assertSee('Junaid')->assertDontSee('Haji Sharif');
        $this->get(route('statement', ['id' => $supplier->id]))->assertOk()->assertSee('2100-001-AFN');
    }
}
