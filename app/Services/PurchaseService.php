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
 *   request  what the factory needs to buy, before a supplier is chosen: nothing is posted
 *   quotation  a supplier's price offer: nothing is posted
 *   order    a purchase order (PO): a promise, nothing is posted
 *   invoice  the supplier's bill: we owe the supplier, stock value rises;
 *            the goods enter the warehouse when they arrive (GRN)
 *   return   goods sent back: stock leaves, the supplier owes us
 *
 * Transport, customs and other costs are added to the cost of the goods, shared by weight
 * (the weight of one unit of each item); items without a weight share them by value.
 */
class PurchaseService
{
    public const PREFIX = ['request' => 'PRQ', 'quotation' => 'PQ', 'order' => 'PO', 'invoice' => 'PI', 'return' => 'PR'];

    /** Papers that are only an offer or a promise: nothing is posted. */
    public const OPEN_TYPES = ['request', 'quotation', 'order'];

    /**
     * @param  array  $data  type, date, supplier_id, warehouse_id, lines[], costs[], discount, paid, paid_from_id, received, ...
     */
    public static function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $supplier = empty($data['supplier_id']) ? null : Account::with('type')->findOrFail($data['supplier_id']);
            if ($supplier && $supplier->type->key !== 'supplier') {
                throw new UserError(__('Choose a supplier.'));
            }
            if (! $supplier && $type !== 'request') {
                throw new UserError(__('Choose a supplier.'));
            }
            // the paper's currency is the supplier account's currency; a request without a supplier uses the one chosen
            $currencyId = $supplier?->currency_id ?? (int) ($data['currency_id'] ?? 0 ?: Rates::base()->id);
            if ($supplier && ! empty($data['currency_id']) && (int) $data['currency_id'] !== $supplier->currency_id) {
                throw new UserError(__(':name has an account in :code. Choose that currency or another account of the supplier.', ['name' => $supplier->name, 'code' => $supplier->currency->code]));
            }
            $date = $data['date'];
            $rate = Rates::rate($currencyId, $date);

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
                'supplier_id' => $supplier?->id,
                'warehouse_id' => $data['warehouse_id'],
                'currency_id' => $currencyId,
                'rate' => $rate,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'quotation_no' => $data['quotation_no'] ?? null,
                'status' => in_array($type, self::OPEN_TYPES, true) ? 'open' : 'posted',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'paid_from_id' => Num::isPositive($paid) ? $data['paid_from_id'] : null,
                'parent_id' => $data['parent_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (in_array($type, self::OPEN_TYPES, true)) {
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

            // every line carries its share of the bill (by value) and of the extra costs (by weight)
            $qtySum = Num::sum(array_column($lines, 'qty'));
            $weights = self::weights($lines);
            $weightSum = Num::sum($weights);
            foreach ($lines as $n => &$line) {
                $valueShare = Num::isPositive($subtotal) ? Num::div($line['total'], $subtotal) : Num::div($line['qty'], $qtySum);
                $costShare = Num::isPositive($weightSum) ? Num::div($weights[$n], $weightSum) : $valueShare;
                $lineBase = Num::add(Num::mul($totalBase, $valueShare), Num::mul($costsBase, $costShare));
                $line['unit_cost_base'] = Num::round(Num::div($lineBase, $line['qty']), 6);
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

    /**
     * Weight of each line: quantity x the weight of one unit. Items counted in kg, grams or tons need no weight.
     * When an item has no weight, all lines share the costs by value instead (returns zeros).
     *
     * @return array<int, string>
     */
    public static function weights(array $lines): array
    {
        $items = Item::with('unit')->whereIn('id', array_column($lines, 'item_id'))->get()->keyBy('id');
        $weights = [];
        foreach ($lines as $n => $line) {
            $item = $items[$line['item_id']] ?? null;
            $each = Num::isPositive($item?->weight ?? 0) ? (string) $item->weight : self::kilosInUnit($item);
            if (! Num::isPositive($each)) {
                return array_fill_keys(array_keys($lines), '0');
            }
            $weights[$n] = Num::mul($line['qty'], $each);
        }

        return $weights;
    }

    /** Kilograms in one unit for items counted by weight (kg, gram, ton); "0" for anything else. */
    private static function kilosInUnit(?Item $item): string
    {
        $unit = mb_strtolower(trim((string) ($item?->unit?->symbol ?: $item?->unit?->name)));

        return match (true) {
            in_array($unit, ['kg', 'kgs', 'kilo', 'kilogram', 'kilograms', 'کیلو', 'کیلوگرام'], true) => '1',
            in_array($unit, ['g', 'gram', 'grams', 'گرام'], true) => '0.001',
            in_array($unit, ['t', 'ton', 'tons', 'tonne', 'تن'], true) => '1000',
            default => '0',
        };
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
