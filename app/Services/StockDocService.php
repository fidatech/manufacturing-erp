<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Item;
use App\Models\StockDoc;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Stock papers that are not a purchase or a sale.
 *   opening   stock that was already in the warehouse when the system started
 *   transfer  goods moved from one warehouse to another
 *   count     physical count: the system is corrected to what was really found
 */
class StockDocService
{
    public const PREFIX = ['opening' => 'OS', 'transfer' => 'ST', 'count' => 'SC'];

    public static function create(array $data): StockDoc
    {
        return DB::transaction(function () use ($data) {
            $type = $data['type'];
            $date = $data['date'];
            $warehouseId = (int) $data['warehouse_id'];
            $toWarehouseId = $type === 'transfer' ? (int) ($data['to_warehouse_id'] ?? 0) : null;
            if ($type === 'transfer' && (! $toWarehouseId || $toWarehouseId === $warehouseId)) {
                throw new UserError(__('Choose two different warehouses.'));
            }

            $doc = StockDoc::create([
                'type' => $type,
                'number' => Sequence::next(self::PREFIX[$type]),
                'date' => $date,
                'warehouse_id' => $warehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $gain = '0';   // value added to stock
            $loss = '0';   // value removed from stock
            $count = 0;

            foreach ($data['lines'] ?? [] as $line) {
                if (empty($line['item_id']) || ! isset($line['qty']) || $line['qty'] === '' || $line['qty'] === null) {
                    continue;
                }
                $item = Item::findOrFail($line['item_id']);
                $qty = Num::qty($line['qty']);
                if (Num::isNegative($qty)) {
                    throw new UserError(__('app.errors.qty_positive', ['item' => $item->name]));
                }

                if ($type === 'opening') {
                    if (! Num::isPositive($qty)) {
                        continue;
                    }
                    $cost = Num::round($line['unit_cost'] ?? 0, 6);
                    $batch = ['batch_no' => $line['batch_no'] ?? '', 'mfg_date' => ($line['mfg_date'] ?? null) ?: null, 'expiry_date' => ($line['expiry_date'] ?? null) ?: null];
                    $gain = Num::add($gain, Stock::receive($item, $warehouseId, $qty, $cost, $date, 'opening', $doc, $batch, $doc->number));
                    $doc->lines()->create(['item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost] + $batch);
                } elseif ($type === 'transfer') {
                    if (! Num::isPositive($qty)) {
                        continue;
                    }
                    $out = Stock::issue($item, $warehouseId, $qty, $date, 'transfer_out', $doc, $doc->number);
                    foreach ($out['batches'] as $batch) {
                        Stock::receive($item, $toWarehouseId, $batch['qty'], $item->avg_cost, $date, 'transfer_in', $doc, $batch, $doc->number, reprice: false);
                    }
                    $doc->lines()->create(['item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $item->avg_cost]);
                } else {
                    $system = Stock::onHand($item->id, $warehouseId);
                    $difference = Num::qty(Num::sub($qty, $system));
                    if (Num::isPositive($difference)) {
                        $gain = Num::add($gain, Stock::receive($item, $warehouseId, $difference, $item->avg_cost, $date, 'count', $doc, [], $doc->number, reprice: false));
                    } elseif (Num::isNegative($difference)) {
                        $loss = Num::add($loss, Stock::issue($item, $warehouseId, Num::abs($difference), $date, 'count', $doc, $doc->number)['cost']);
                    }
                    $doc->lines()->create(['item_id' => $item->id, 'qty' => $qty, 'system_qty' => $system, 'unit_cost' => $item->avg_cost]);
                }
                $count++;
            }

            if ($count === 0) {
                throw new UserError(__('Add at least one item with a quantity.'));
            }

            $gain = Num::money($gain);
            $loss = Num::money($loss);
            $inventory = Account::system('inventory');
            if ($type === 'opening' && Num::isPositive($gain)) {
                Ledger::post('opening', $date, __('Opening stock :number', ['number' => $doc->number]), [
                    ['account' => $inventory, 'debit' => $gain],
                    ['account' => Account::system('opening'), 'credit' => $gain],
                ], $doc);
            }
            if ($type === 'count') {
                $adjustment = Account::system('stock_adjustment');
                if (Num::isPositive($gain)) {
                    Ledger::post('stock_count', $date, __('Stock found in count :number', ['number' => $doc->number]), [
                        ['account' => $inventory, 'debit' => $gain], ['account' => $adjustment, 'credit' => $gain],
                    ], $doc);
                }
                if (Num::isPositive($loss)) {
                    Ledger::post('stock_count', $date, __('Stock missing in count :number', ['number' => $doc->number]), [
                        ['account' => $adjustment, 'debit' => $loss], ['account' => $inventory, 'credit' => $loss],
                    ], $doc);
                }
            }

            return $doc;
        });
    }

    public static function cancel(StockDoc $doc): void
    {
        DB::transaction(function () use ($doc) {
            if ($doc->status === 'cancelled') {
                return;
            }
            Stock::voidSource($doc);
            Ledger::voidSource($doc);
            $doc->update(['status' => 'cancelled']);
        });
    }
}
