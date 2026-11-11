<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Item;
use App\Models\JournalLine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockDoc;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Ledger;
use App\Services\Stock;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradeTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Warehouse $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->warehouse = Warehouse::create(['name' => 'Main warehouse']);
        $this->second = Warehouse::create(['name' => 'Final products']);
    }

    private function item(string $name, string $type = 'raw', array $extra = []): Item
    {
        return Item::create(['type' => $type, 'name' => $name, 'unit_id' => Unit::where('name', 'Kilogram')->value('id')] + $extra);
    }

    private function assertBooksBalance(): void
    {
        $sums = JournalLine::where('is_void', false)->selectRaw('SUM(base_debit) as d, SUM(base_credit) as c')->toBase()->first();
        $this->assertSame(number_format((float) $sums->d, 2, '.', ''), number_format((float) $sums->c, 2, '.', ''), 'Debits and credits differ.');
    }

    private function buy(Item $item, string $qty, string $price, array $extra = []): Purchase
    {
        $supplier = Account::ofType('supplier')->first() ?? $this->account('supplier', 'Javed', 'USD');
        $this->post(route('purchases.store'), $extra + [
            'type' => 'invoice', 'date' => '2026-10-01', 'supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'received' => '1',
            'lines' => [['item_id' => $item->id, 'qty' => $qty, 'price' => $price]],
        ])->assertRedirect();

        return Purchase::orderByDesc('id')->firstOrFail();
    }

    public function test_every_trade_and_stock_page_opens(): void
    {
        foreach (['invoice', 'order', 'return'] as $type) {
            $this->get(route('purchases.index', ['type' => $type]))->assertOk();
            $this->get(route('purchases.create', ['type' => $type]))->assertOk();
        }
        foreach (['invoice', 'quotation', 'return'] as $type) {
            $this->get(route('sales.index', ['type' => $type]))->assertOk();
            $this->get(route('sales.create', ['type' => $type]))->assertOk();
        }
        foreach (['transfer', 'count', 'opening'] as $type) {
            $this->get(route('stock-docs.index', ['type' => $type]))->assertOk();
            $this->get(route('stock-docs.create', ['type' => $type]))->assertOk();
        }
        $this->get(route('stock-docs.create', ['type' => 'count', 'warehouse' => $this->warehouse->id]))->assertOk();
        $this->get(route('stock.index'))->assertOk();
        $this->get(route('stock.batches'))->assertOk();
    }

    public function test_a_purchase_with_extra_costs_and_a_payment(): void
    {
        $this->rate('AFN', '70');
        $supplier = $this->account('supplier', 'Javed', 'USD');
        $acid = $this->item('Citric Acid');

        // 100 kg at 2 USD, 7,000 AFN transport (= 100 USD) paid from the AFN cash box, 50 USD paid now
        $purchase = $this->buy($acid, '100', '2', [
            'costs' => [['name' => 'Transport', 'amount' => '7000', 'paid_from_id' => $this->cash('AFN')->id]],
            'paid' => '50', 'paid_from_id' => $this->cash('USD')->id,
        ]);

        $this->assertSame('200.00', Num::money($purchase->total));
        $this->assertSame('100.0000', Stock::onHand($acid->id));
        $this->assertSame('3.000000', Num::round($acid->fresh()->avg_cost, 6));                          // (200 + 100) / 100 kg
        $this->assertSame('300.00', Ledger::balance(Account::system('inventory')->id));
        $this->assertSame('-150.00', Ledger::balance($supplier->id));                      // we still owe 150
        $this->assertSame('-7000.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('-50.00', Ledger::balance($this->cash('USD')->id));
        $this->assertBooksBalance();

        $this->get(route('purchases.show', ['id' => $purchase->id]))->assertOk()->assertSee('Transport')->assertSee($purchase->grn_number);
        $this->get(route('stock.card', ['id' => $acid->id]))->assertOk()->assertSee($purchase->number);
    }

    public function test_goods_on_the_way_enter_the_warehouse_when_received(): void
    {
        $acid = $this->item('Citric Acid');
        $purchase = $this->buy($acid, '40', '5', ['received' => '0']);

        $this->assertSame('0.0000', Stock::onHand($acid->id));
        $this->assertNull($purchase->received_date);
        $this->get(route('purchases.index', ['type' => 'invoice', 'waiting' => 1]))->assertOk()->assertSee($purchase->number);

        $this->post(route('purchases.receive', ['id' => $purchase->id]), ['date' => '2026-10-05'])->assertRedirect();

        $this->assertSame('40.0000', Stock::onHand($acid->id, $this->warehouse->id));
        $this->assertNotNull($purchase->fresh()->grn_number);

        // receiving twice is refused
        $this->post(route('purchases.receive', ['id' => $purchase->id]), ['date' => '2026-10-06'])->assertSessionHas('danger');
        $this->assertSame('40.0000', Stock::onHand($acid->id));
    }

    public function test_a_purchase_order_becomes_a_purchase(): void
    {
        $supplier = $this->account('supplier', 'Javed', 'USD');
        $acid = $this->item('Citric Acid');

        $this->post(route('purchases.store'), ['type' => 'order', 'date' => '2026-10-01', 'supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['item_id' => $acid->id, 'qty' => '10', 'price' => '2']]])->assertRedirect();
        $order = Purchase::where('type', 'order')->firstOrFail();
        $this->assertSame('open', $order->status);
        $this->assertSame('0.0000', Stock::onHand($acid->id));
        $this->assertDatabaseCount('journal_entries', 0);

        $this->get(route('purchases.create', ['type' => 'invoice', 'from' => $order->id]))->assertOk()->assertSee('Citric Acid');
        $this->buy($acid, '10', '2', ['parent_id' => $order->id]);

        $this->assertSame('converted', $order->fresh()->status);
        $this->assertSame('10.0000', Stock::onHand($acid->id));
    }

    public function test_a_sale_like_the_clients_bill(): void
    {
        $customer = $this->account('customer', 'Shaheer', 'USD', ['phone' => '0790000000', 'address' => 'Takhar']);
        $mango = $this->item('Mango KC9012', 'product', ['packing' => 'Can 20 kg']);
        $gum = $this->item('Juice Gum');
        $this->buy($mango, '50', '40');
        $this->buy($gum, '100', '3');

        // the customer already owes 255 from before
        $this->post(route('money.store', ['kind' => 'out']), ['date' => '2026-10-02', 'money_id' => $this->cash('USD')->id, 'other_id' => $customer->id, 'amount' => '255']);

        $this->post(route('sales.store'), [
            'type' => 'invoice', 'date' => '2026-10-27', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['item_id' => $mango->id, 'qty' => '15', 'price' => '65'],
                ['item_id' => $gum->id, 'qty' => '45', 'price' => '0'],   // given free with the cans
            ],
        ])->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->assertSame('975.00', Num::money($sale->total));
        $this->assertSame('255.00', Num::money($sale->previous_balance));
        $this->assertSame('735.00', Num::money($sale->cost_base));                        // 15 x 40 + 45 x 3
        $this->assertSame('35.0000', Stock::onHand($mango->id));
        $this->assertSame('55.0000', Stock::onHand($gum->id));
        $this->assertSame('1230.00', Ledger::balance($customer->id));          // 255 + 975
        $this->assertSame('-975.00', Ledger::balance(Account::system('sales')->id));
        $this->assertSame('735.00', Ledger::balance(Account::system('cogs')->id));
        $this->assertBooksBalance();

        $this->get(route('sales.show', ['id' => $sale->id]))->assertOk()->assertSee('Mango KC9012');
        $this->get(route('sales.print', ['id' => $sale->id]))->assertOk()->assertSee('975.00')->assertSee('255.00')->assertSee('1,230.00')->assertSee('Takhar');
        $this->get(route('sales.print', ['id' => $sale->id, 'paper' => 'delivery']))->assertOk()->assertSee('Delivery Note')->assertDontSee('975.00');

        // delivery tracking
        $this->post(route('sales.delivery', ['id' => $sale->id]), ['delivery_status' => 'dispatched', 'date' => '2026-10-28', 'transport_no' => 'KBL-123'])->assertRedirect();
        $this->assertSame('dispatched', $sale->fresh()->delivery_status);
        $this->post(route('sales.delivery', ['id' => $sale->id]), ['delivery_status' => 'delivered', 'date' => '2026-10-29'])->assertRedirect();
        $this->assertSame('2026-10-29', $sale->fresh()->delivered_date->format('Y-m-d'));
        $this->assertSame('KBL-123', $sale->fresh()->transport_no);
    }

    public function test_stock_can_never_go_below_zero(): void
    {
        $customer = $this->account('customer', 'Shaheer', 'USD');
        $mango = $this->item('Mango KC9012', 'product');
        $this->buy($mango, '5', '40');

        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-10-27', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '6', 'price' => '65']]])->assertSessionHas('danger');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame('5.0000', Stock::onHand($mango->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));
    }

    public function test_a_sale_in_afghani_with_money_received_and_a_return(): void
    {
        $this->rate('AFN', '70');
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');
        $mango = $this->item('Mango KC9012', 'product');
        $this->buy($mango, '10', '40');

        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-10-27', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'received' => '3500', 'received_in_id' => $this->cash('AFN')->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '2', 'price' => '3500']]])->assertRedirect();
        $sale = Sale::firstOrFail();

        $this->assertSame('3500.00', Ledger::balance($customer->id));                     // 7,000 billed, 3,500 received
        $this->assertSame('3500.00', Ledger::balance($this->cash('AFN')->id));
        $this->assertSame('-100.00', Ledger::balance(Account::system('sales')->id));      // 7,000 AFN = 100 USD

        // one can comes back
        $this->post(route('sales.store'), ['type' => 'return', 'date' => '2026-10-28', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'parent_id' => $sale->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '1', 'price' => '3500']]])->assertRedirect();

        $this->assertSame('9.0000', Stock::onHand($mango->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));
        $this->assertSame('-50.00', Ledger::balance(Account::system('sales')->id));
        $this->assertSame('40.00', Ledger::balance(Account::system('cogs')->id));
        $this->assertBooksBalance();
    }

    public function test_cancelling_a_sale_puts_everything_back(): void
    {
        $customer = $this->account('customer', 'Shaheer', 'USD');
        $mango = $this->item('Mango KC9012', 'product');
        $purchase = $this->buy($mango, '10', '40');

        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-10-27', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'received' => '100', 'received_in_id' => $this->cash('USD')->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '4', 'price' => '65']]]);
        $sale = Sale::firstOrFail();

        // the purchase cannot be cancelled while part of it is sold
        $this->post(route('purchases.cancel', ['id' => $purchase->id]))->assertSessionHas('danger');
        $this->assertSame('posted', $purchase->fresh()->status);

        $this->post(route('sales.cancel', ['id' => $sale->id]))->assertRedirect();
        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('10.0000', Stock::onHand($mango->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));
        $this->assertSame('0.00', Ledger::balance($this->cash('USD')->id));

        $this->post(route('purchases.cancel', ['id' => $purchase->id]))->assertRedirect();
        $this->assertSame('0.0000', Stock::onHand($mango->id));
        $this->assertBooksBalance();
    }

    public function test_a_quotation_becomes_a_sale(): void
    {
        $customer = $this->account('customer', 'Shaheer', 'USD');
        $mango = $this->item('Mango KC9012', 'product');
        $this->buy($mango, '10', '40');

        $this->post(route('sales.store'), ['type' => 'quotation', 'date' => '2026-10-20', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '3', 'price' => '65']]])->assertRedirect();
        $quotation = Sale::where('type', 'quotation')->firstOrFail();
        $this->assertSame('10.0000', Stock::onHand($mango->id));
        $this->assertSame('0.00', Ledger::balance($customer->id));

        $this->get(route('sales.create', ['type' => 'invoice', 'from' => $quotation->id]))->assertOk();
        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-10-21', 'customer_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'parent_id' => $quotation->id,
            'lines' => [['item_id' => $mango->id, 'qty' => '3', 'price' => '65']]])->assertRedirect();

        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame('7.0000', Stock::onHand($mango->id));
    }

    public function test_opening_stock_transfer_oldest_expiry_first_and_physical_count(): void
    {
        $flavor = $this->item('Cola Flavor');

        $this->post(route('stock-docs.store', ['type' => 'opening']), ['date' => '2026-01-01', 'warehouse_id' => $this->warehouse->id, 'lines' => [
            ['item_id' => $flavor->id, 'qty' => '10', 'unit_cost' => '4', 'batch_no' => 'NEW', 'expiry_date' => '2028-01-01'],
            ['item_id' => $flavor->id, 'qty' => '6', 'unit_cost' => '4', 'batch_no' => 'OLD', 'expiry_date' => '2027-01-01'],
        ]])->assertRedirect();
        $this->assertSame('16.0000', Stock::onHand($flavor->id));
        $this->assertSame('64.00', Ledger::balance(Account::system('inventory')->id));

        // moving 8 takes the 6 that expire first, then 2 of the newer batch
        $this->post(route('stock-docs.store', ['type' => 'transfer']), ['date' => '2026-02-01', 'warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $this->second->id,
            'lines' => [['item_id' => $flavor->id, 'qty' => '8']]])->assertRedirect();
        $this->assertSame('8.0000', Stock::onHand($flavor->id, $this->warehouse->id));
        $this->assertSame('6.0000', Stock::onHand($flavor->id, $this->second->id, 'OLD'));
        $this->assertSame('2.0000', Stock::onHand($flavor->id, $this->second->id, 'NEW'));
        $this->assertSame('0.0000', Stock::onHand($flavor->id, $this->warehouse->id, 'OLD'));
        $this->get(route('stock.batches'))->assertOk()->assertSee('OLD');

        // the count finds only 7 in the main warehouse: 1 kg (4 USD) is written off
        $this->post(route('stock-docs.store', ['type' => 'count']), ['date' => '2026-03-01', 'warehouse_id' => $this->warehouse->id,
            'lines' => [['item_id' => $flavor->id, 'qty' => '7']]])->assertRedirect();
        $count = StockDoc::where('type', 'count')->firstOrFail();
        $this->assertSame('7.0000', Stock::onHand($flavor->id, $this->warehouse->id));
        $this->assertSame('60.00', Ledger::balance(Account::system('inventory')->id));
        $this->assertSame('4.00', Ledger::balance(Account::system('stock_adjustment')->id));
        $this->get(route('stock-docs.show', ['type' => 'count', 'id' => $count->id]))->assertOk();
        $this->get(route('stock.index', ['low' => 1]))->assertOk();
        $this->assertBooksBalance();
    }
}
