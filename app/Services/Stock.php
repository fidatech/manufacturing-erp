<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one stock ledger behind every screen.
 *
 * Rules: stock can never go below zero; goods leave oldest expiry first;
 * cost is the weighted average of everything bought (in the main currency).
 * Every method must run inside a database transaction.
 */
class Stock
{
    /** Quantity on hand of an item (in one warehouse, or everywhere). */
    public static function onHand(int $itemId, ?int $warehouseId = null, ?string $batch = null): string
    {
        $row = StockMovement::where('item_id', $itemId)
            ->where('is_void', false)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($batch !== null, fn ($q) => $q->where('batch_no', $batch))
            ->selectRaw('COALESCE(SUM(qty_in),0) as qin, COALESCE(SUM(qty_out),0) as qout')
            ->toBase()
            ->first();

        return Num::qty(Num::sub($row->qin, $row->qout));
    }

    /** Goods come in. Returns the value added, in the main currency. */
    public static function receive(Item|int $item, int $warehouseId, mixed $qty, mixed $unitCost, string $date, string $type, ?Model $source = null, array $batch = [], ?string $memo = null, bool $reprice = true): string
    {
        $item = self::lock($item);
        $qty = Num::qty($qty);
        if (! Num::isPositive($qty)) {
            throw new UserError(__('app.errors.qty_positive', ['item' => $item->name]));
        }
        $unitCost = Num::round($unitCost, 6);

        if ($reprice) {
            $have = self::onHand($item->id);
            $newAverage = Num::isPositive($have)
                ? Num::div(Num::add(Num::mul($have, $item->avg_cost), Num::mul($qty, $unitCost)), Num::add($have, $qty))
                : $unitCost;
            $item->forceFill(['avg_cost' => Num::round($newAverage, 6)])->saveQuietly();
        }

        StockMovement::create([
            'date' => $date,
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'batch_no' => trim((string) ($batch['batch_no'] ?? '')),
            'mfg_date' => $batch['mfg_date'] ?? null,
            'expiry_date' => $batch['expiry_date'] ?? null,
            'qty_in' => $qty,
            'unit_cost' => $unitCost,
            'type' => $type,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'memo' => $memo,
            'created_by' => auth()->id(),
        ]);

        return Num::money(Num::mul($qty, $unitCost));
    }

    /**
     * Goods go out, oldest expiry first. Returns the cost of what left, in the main currency.
     *
     * @return array{cost: string, batches: array<int, array{batch_no: string, mfg_date: ?string, expiry_date: ?string, qty: string}>}
     */
    public static function issue(Item|int $item, int $warehouseId, mixed $qty, string $date, string $type, ?Model $source = null, ?string $memo = null, ?string $onlyBatch = null): array
    {
        $item = self::lock($item);
        $qty = Num::qty($qty);
        if (! Num::isPositive($qty)) {
            throw new UserError(__('app.errors.qty_positive', ['item' => $item->name]));
        }

        $have = self::onHand($item->id, $warehouseId, $onlyBatch);
        if (Num::cmp($have, $qty) < 0) {
            throw new UserError(__('app.errors.not_enough_stock', [
                'item' => $item->name,
                'warehouse' => Warehouse::find($warehouseId)?->name ?? '',
                'have' => Num::format($have, 4, true),
                'need' => Num::format($qty, 4, true),
            ]));
        }

        $left = $qty;
        $taken = [];
        foreach (self::batches($item->id, $warehouseId, $onlyBatch) as $batch) {
            if (! Num::isPositive($left)) {
                break;
            }
            $take = Num::cmp($batch['qty'], $left) >= 0 ? $left : $batch['qty'];
            StockMovement::create([
                'date' => $date,
                'item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'batch_no' => $batch['batch_no'],
                'mfg_date' => $batch['mfg_date'],
                'expiry_date' => $batch['expiry_date'],
                'qty_out' => $take,
                'unit_cost' => $item->avg_cost,
                'type' => $type,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'memo' => $memo,
                'created_by' => auth()->id(),
            ]);
            $taken[] = ['qty' => $take] + $batch;
            $left = Num::qty(Num::sub($left, $take));
        }

        return ['cost' => Num::money(Num::mul($qty, $item->avg_cost)), 'batches' => $taken];
    }

    /**
     * Batches of an item that still have stock in a warehouse, oldest expiry first.
     *
     * @return array<int, array{batch_no: string, mfg_date: ?string, expiry_date: ?string, qty: string}>
     */
    public static function batches(int $itemId, int $warehouseId, ?string $onlyBatch = null): array
    {
        $rows = StockMovement::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('is_void', false)
            ->when($onlyBatch !== null, fn ($q) => $q->where('batch_no', $onlyBatch))
            ->groupBy('batch_no')
            ->selectRaw('batch_no, MIN(mfg_date) as mfg_date, MIN(expiry_date) as expiry_date, SUM(qty_in) - SUM(qty_out) as qty, MIN(id) as first_id')
            ->toBase()
            ->get()
            ->filter(fn ($row) => Num::isPositive($row->qty))
            ->sort(function ($a, $b) {
                // no expiry date sorts last
                $ea = $a->expiry_date ?? '9999-12-31';
                $eb = $b->expiry_date ?? '9999-12-31';

                return $ea === $eb ? $a->first_id <=> $b->first_id : strcmp($ea, $eb);
            });

        return $rows->map(fn ($row) => [
            'batch_no' => (string) $row->batch_no,
            'mfg_date' => $row->mfg_date ? substr((string) $row->mfg_date, 0, 10) : null,
            'expiry_date' => $row->expiry_date ? substr((string) $row->expiry_date, 0, 10) : null,
            'qty' => Num::qty($row->qty),
        ])->values()->all();
    }

    /** Cancel every movement a paper created. Refuses if that would leave stock below zero. */
    public static function voidSource(Model $source): void
    {
        $movements = StockMovement::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('is_void', false)
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        foreach ($movements->pluck('item_id')->unique() as $itemId) {
            self::lock($itemId);
        }
        StockMovement::whereIn('id', $movements->pluck('id'))->update(['is_void' => true]);

        foreach ($movements->where('qty_in', '>', 0) as $movement) {
            if (Num::isNegative(self::onHand($movement->item_id, $movement->warehouse_id, $movement->batch_no))) {
                throw new UserError(__('app.errors.cancel_would_go_negative', ['item' => $movement->item?->name ?? '']));
            }
        }
    }

    private static function lock(Item|int $item): Item
    {
        return Item::whereKey($item instanceof Item ? $item->id : $item)->lockForUpdate()->firstOrFail();
    }
}
