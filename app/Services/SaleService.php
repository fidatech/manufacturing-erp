<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Sale;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Sales.
 *   quotation  a price offer: nothing is posted
 *   order      the customer's order: nothing is posted until it becomes a bill
 *   invoice    the bill: the customer owes us, the goods leave the warehouse
 *   return     goods that came back: stock returns, the customer owes less
 */
class SaleService
{
    public const PREFIX = ['quotation' => 'SQ', 'order' => 'SO', 'invoice' => 'SI', 'return' => 'SR'];

    /** Papers that are only an offer or a promise: nothing is posted. */
    public const OPEN_TYPES = ['quotation', 'order'];

    /**
     * @param  array  $data  type, date, customer_id, warehouse_id, lines[], discount, received, received_in_id, ...
     */
    public static function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $customer = Account::with('type')->findOrFail($data['customer_id']);
            $date = $data['date'];
            $rate = Rates::rate($customer->currency_id, $date);

            $lines = [];
            $subtotal = '0';
            foreach ($data['lines'] ?? [] as $line) {
                if (empty($line['item_id']) || ! Num::isPositive($line['qty'] ?? 0)) {
                    continue;
                }
                $qty = Num::qty($line['qty']);
                $price = Num::round($line['price'] ?? 0, 6); // 0 = given free
                $total = Num::money(Num::mul($qty, $price));
                $subtotal = Num::add($subtotal, $total);
                $lines[] = ['item_id' => (int) $line['item_id'], 'description' => $line['description'] ?? null, 'qty' => $qty, 'price' => $price, 'total' => $total];
            }
            if (! $lines) {
                throw new UserError(__('Add at least one item with a quantity.'));
            }

            $subtotal = Num::money($subtotal);
            $discount = Num::money($data['discount'] ?? 0);
            $total = Num::money(Num::sub($subtotal, $discount));
            if (Num::isNegative($total)) {
                throw new UserError(__('The discount is bigger than the total.'));
            }
            $received = $type === 'invoice' ? Num::money($data['received'] ?? 0) : '0.00';
            if (Num::isPositive($received) && empty($data['received_in_id'])) {
                throw new UserError(__('Choose where the received money was put.'));
            }

            $sale = Sale::create([
                'type' => $type,
                'number' => Sequence::next(self::PREFIX[$type]),
                'date' => $date,
                'customer_id' => $customer->id,
                'warehouse_id' => $data['warehouse_id'],
                'currency_id' => $customer->currency_id,
                'rate' => $rate,
                'status' => in_array($type, self::OPEN_TYPES, true) ? 'open' : 'posted',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'received' => $received,
                'received_in_id' => Num::isPositive($received) ? $data['received_in_id'] : null,
                'previous_balance' => Ledger::balance($customer->id),
                'delivery_address' => $data['delivery_address'] ?? $customer->address,
                'transport_no' => $data['transport_no'] ?? null,
                'driver' => $data['driver'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (in_array($type, self::OPEN_TYPES, true)) {
                $sale->lines()->createMany($lines);

                return $sale;
            }

            $totalBase = Rates::toBase($total, $customer->currency_id, $date, $rate);
            $inventory = Account::system('inventory');
            $cogs = Account::system('cogs');
            $salesAccount = Account::system('sales');
            $costTotal = '0';

            foreach ($lines as &$line) {
                if ($type === 'invoice') {
                    $out = Stock::issue($line['item_id'], $sale->warehouse_id, $line['qty'], $date, 'sale', $sale, $sale->number);
                    $line['cost_base'] = $out['cost'];
                } else {
                    // goods come back at today's average cost; the average itself does not move
                    $item = \App\Models\Item::findOrFail($line['item_id']);
                    $line['cost_base'] = Stock::receive($item, $sale->warehouse_id, $line['qty'], $item->avg_cost, $date, 'sale_return', $sale, [], $sale->number, reprice: false);
                }
                $costTotal = Num::add($costTotal, $line['cost_base']);
            }
            unset($line);
            $costTotal = Num::money($costTotal);
            $sale->lines()->createMany($lines);
            $sale->update(['cost_base' => $costTotal]);

            $entry = [];
            if ($type === 'invoice') {
                $entry[] = ['account' => $customer, 'debit' => $total, 'base' => $totalBase];
                $entry[] = ['account' => $salesAccount, 'credit' => $totalBase];
                $entry[] = ['account' => $cogs, 'debit' => $costTotal];
                $entry[] = ['account' => $inventory, 'credit' => $costTotal];
                $text = __('Sale :number to :name', ['number' => $sale->number, 'name' => $customer->name]);
            } else {
                $entry[] = ['account' => $salesAccount, 'debit' => $totalBase];
                $entry[] = ['account' => $customer, 'credit' => $total, 'base' => $totalBase];
                $entry[] = ['account' => $inventory, 'debit' => $costTotal];
                $entry[] = ['account' => $cogs, 'credit' => $costTotal];
                $text = __('Sale return :number from :name', ['number' => $sale->number, 'name' => $customer->name]);
            }
            if (Num::isPositive($totalBase) || Num::isPositive($costTotal)) {
                Ledger::post($type === 'invoice' ? 'sale' : 'sale_return', $date, $text, $entry, $sale);
            }

            if (Num::isPositive($received)) {
                $into = Account::findOrFail($data['received_in_id']);
                $base = Rates::toBase($received, $customer->currency_id, $date, $rate);
                $intoAmount = $into->currency_id === $customer->currency_id
                    ? $received
                    : ($into->currency_id === Rates::base()->id ? $base : Rates::fromBase($base, $into->currency_id, $date));
                Ledger::post('receipt', $date, __('Received for sale :number', ['number' => $sale->number]), [
                    ['account' => $into, 'debit' => $intoAmount, 'base' => $base],
                    ['account' => $customer, 'credit' => $received, 'base' => $base],
                ], $sale);
            }

            return $sale;
        });
    }

    public static function cancel(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            if ($sale->status === 'cancelled') {
                return;
            }
            Stock::voidSource($sale);
            Ledger::voidSource($sale);
            $sale->update(['status' => 'cancelled']);
        });
    }
}
