<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Item;
use App\Models\Purchase;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Purchases.
 *   order    a purchase order (PO): a promise, nothing is posted
 *   invoice  the supplier's bill: we owe the supplier, stock value rises;
 *            the goods enter the warehouse when they arrive (GRN)
 *   return   goods sent back: stock leaves, the supplier owes us
 *
 * Transport, customs and other costs are added to the cost of the goods.
 */
class PurchaseService
{
    public const PREFIX = ['order' => 'PO', 'invoice' => 'PI', 'return' => 'PR'];

    /**
     * @param  array  $data  type, date, supplier_id, warehouse_id, lines[], costs[], discount, paid, paid_from_id, received, ...
     */
    public static function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $supplier = Account::with('type')->findOrFail($data['supplier_id']);
            $date = $data['date'];
            $rate = Rates::rate($supplier->currency_id, $date);

            $lines = [];
            $subtotal = '0';
            foreach ($data['lines'] ?? [] as $line) {
                if (empty($line['item_id']) || ! Num::isPositive($line['qty'] ?? 0)) {
                    continue;
                }
                $qty = Num::qty($line['qty']);
                $price = Num::round($line['price'] ?? 0, 6);
                $total = Num::money(Num::mul($qty, $price));
                $subtotal = Num::add($subtotal, $total);
                $lines[] = [
                    'item_id' => (int) $line['item_id'],
                    'batch_no' => trim((string) ($line['batch_no'] ?? '')),
                    'mfg_date' => $line['mfg_date'] ?? null ?: null,
                    'expiry_date' => $line['expiry_date'] ?? null ?: null,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $total,
                ];
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
            $paid = $type === 'invoice' ? Num::money($data['paid'] ?? 0) : '0.00';
            if (Num::isPositive($paid) && empty($data['paid_from_id'])) {
                throw new UserError(__('Choose where the payment was made from.'));
            }

            $purchase = Purchase::create([
                'type' => $type,
                'number' => Sequence::next(self::PREFIX[$type]),
                'date' => $date,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $data['warehouse_id'],
                'currency_id' => $supplier->currency_id,
                'rate' => $rate,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'status' => $type === 'order' ? 'open' : 'posted',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'paid_from_id' => Num::isPositive($paid) ? $data['paid_from_id'] : null,
                'parent_id' => $data['parent_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if ($type === 'order') {
                $purchase->lines()->createMany($lines);

                return $purchase;
            }

            $totalBase = Rates::toBase($total, $supplier->currency_id, $date, $rate);

            if ($type === 'return') {
                $purchase->lines()->createMany($lines);
                self::postReturn($purchase, $supplier, $totalBase);

                return $purchase;
            }

            // extra costs: transport, customs, commission ...
            $costLines = [];
            $costsBase = '0';
            foreach ($data['costs'] ?? [] as $cost) {
                if (empty($cost['name']) || ! Num::isPositive($cost['amount'] ?? 0)) {
                    continue;
                }
                if (empty($cost['paid_from_id'])) {
                    throw new UserError(__('Choose where ":name" was paid from.', ['name' => $cost['name']]));
                }
                $from = Account::findOrFail($cost['paid_from_id']);
                $amount = Num::money($cost['amount']);
                $amountBase = Rates::toBase($amount, $from->currency_id, $date);
                $costsBase = Num::add($costsBase, $amountBase);
                $purchase->costs()->create(['name' => $cost['name'], 'amount' => $amount, 'paid_from_id' => $from->id, 'amount_base' => $amountBase]);
                $costLines[] = ['account' => $from, 'credit' => $amount, 'base' => $amountBase, 'memo' => $cost['name']];
            }
            $costsBase = Num::money($costsBase);
            $valueBase = Num::money(Num::add($totalBase, $costsBase));

            // every line carries its share of the bill and of the extra costs
            $qtySum = Num::sum(array_column($lines, 'qty'));
            foreach ($lines as &$line) {
                $share = Num::isPositive($subtotal) ? Num::div($line['total'], $subtotal) : Num::div($line['qty'], $qtySum);
                $line['unit_cost_base'] = Num::round(Num::div(Num::mul($valueBase, $share), $line['qty']), 6);
            }
            unset($line);
            $purchase->lines()->createMany($lines);
            $purchase->update(['costs_base' => $costsBase]);

            if (Num::isPositive($valueBase)) {
                Ledger::post('purchase', $date, __('Purchase :number from :name', ['number' => $purchase->number, 'name' => $supplier->name]), array_merge([
                    ['account' => Account::system('inventory'), 'debit' => $valueBase],
                    ['account' => $supplier, 'credit' => $total, 'base' => $totalBase],
                ], $costLines), $purchase);
            }

            if (Num::isPositive($paid)) {
                self::pay($purchase, $supplier, $paid, Account::findOrFail($data['paid_from_id']));
            }

            if (! empty($data['received'])) {
                self::receive($purchase, $date);
            }

            return $purchase;
        });
    }

    /** The goods arrived (GRN): they enter the warehouse now. */
    public static function receive(Purchase $purchase, string $date): void
    {
        DB::transaction(function () use ($purchase, $date) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->type !== 'invoice' || $purchase->status !== 'posted' || $purchase->received_date) {
                throw new UserError(__('These goods were already received.'));
            }

            foreach ($purchase->lines as $line) {
                Stock::receive($line->item_id, $purchase->warehouse_id, $line->qty, $line->unit_cost_base, $date, 'purchase', $purchase, [
                    'batch_no' => $line->batch_no,
                    'mfg_date' => $line->mfg_date?->format('Y-m-d'),
                    'expiry_date' => $line->expiry_date?->format('Y-m-d'),
                ], $purchase->number);
            }

            $purchase->update(['received_date' => $date, 'grn_number' => Sequence::next('GRN')]);
        });
    }

    public static function cancel(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'cancelled') {
                return;
            }
            Stock::voidSource($purchase);
            Ledger::voidSource($purchase);
            $purchase->update(['status' => 'cancelled']);
        });
    }

    private static function pay(Purchase $purchase, Account $supplier, string $paid, Account $from): void
    {
        $base = Rates::toBase($paid, $supplier->currency_id, $purchase->date->format('Y-m-d'), (string) $purchase->rate);
        $fromAmount = $from->currency_id === $supplier->currency_id
            ? $paid
            : ($from->currency_id === Rates::base()->id ? $base : Rates::fromBase($base, $from->currency_id, $purchase->date->format('Y-m-d')));

        Ledger::post('payment', $purchase->date->format('Y-m-d'), __('Paid for purchase :number', ['number' => $purchase->number]), [
            ['account' => $supplier, 'debit' => $paid, 'base' => $base],
            ['account' => $from, 'credit' => $fromAmount, 'base' => $base],
        ], $purchase);
    }

    private static function postReturn(Purchase $purchase, Account $supplier, string $totalBase): void
    {
        $date = $purchase->date->format('Y-m-d');
        $cost = '0';
        foreach ($purchase->lines as $line) {
            $out = Stock::issue($line->item_id, $purchase->warehouse_id, $line->qty, $date, 'purchase_return', $purchase, $purchase->number, $line->batch_no !== '' ? $line->batch_no : null);
            $cost = Num::add($cost, $out['cost']);
        }
        $cost = Num::money($cost);
        $difference = Num::money(Num::sub($totalBase, $cost)); // returned for more (or less) than its average cost

        $lines = [
            ['account' => $supplier, 'debit' => $purchase->total, 'base' => $totalBase],
            ['account' => Account::system('inventory'), 'credit' => $cost],
        ];
        if (Num::isPositive($difference)) {
            $lines[] = ['account' => Account::system('stock_adjustment'), 'credit' => $difference];
        } elseif (Num::isNegative($difference)) {
            $lines[] = ['account' => Account::system('stock_adjustment'), 'debit' => Num::abs($difference)];
        }

        Ledger::post('purchase_return', $date, __('Purchase return :number to :name', ['number' => $purchase->number, 'name' => $supplier->name]), $lines, $purchase);
    }
}
