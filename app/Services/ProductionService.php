<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Item;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Making products.
 * One step: say what was made and how many. The raw materials leave the warehouse
 * (oldest expiry first) and the finished product enters stock at what it really cost:
 * the materials used plus the extra cost of the recipe.
 */
class ProductionService
{
    /**
     * @param  array  $data  recipe_id, qty, date, warehouse_id, to_warehouse_id, batch_no, expiry_date, notes,
     *                       used[item id => quantity really used] (optional: defaults to the recipe)
     */
    public static function create(array $data): ProductionBatch
    {
        return DB::transaction(function () use ($data) {
            $recipe = Recipe::with(['lines.item', 'item'])->findOrFail($data['recipe_id']);
            $qty = Num::qty($data['qty']);
            if (! Num::isPositive($qty)) {
                throw new UserError(__('Write how many were made.'));
            }
            if ($recipe->lines->isEmpty()) {
                throw new UserError(__('This recipe has no ingredients yet.'));
            }
            $date = $data['date'];
            $number = Sequence::next('PB');

            $batch = ProductionBatch::create([
                'number' => $number,
                'date' => $date,
                'recipe_id' => $recipe->id,
                'item_id' => $recipe->item_id,
                'warehouse_id' => $data['warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'qty' => $qty,
                'batch_no' => trim((string) ($data['batch_no'] ?? '')) ?: $number,
                'expiry_date' => ($data['expiry_date'] ?? null) ?: ($recipe->shelf_life_months ? date('Y-m-d', strtotime($date.' +'.$recipe->shelf_life_months.' months')) : null),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $materials = '0';
            foreach ($recipe->lines as $line) {
                $needed = Num::qty(Num::mul($line->qty, $qty));
                $used = isset($data['used'][$line->item_id]) && $data['used'][$line->item_id] !== '' && $data['used'][$line->item_id] !== null
                    ? Num::qty($data['used'][$line->item_id])
                    : $needed;
                if (Num::isNegative($used)) {
                    throw new UserError(__('app.errors.qty_positive', ['item' => $line->item->name]));
                }
                if (Num::isZero($used)) {
                    continue;
                }
                $out = Stock::issue($line->item, (int) $data['warehouse_id'], $used, $date, 'production_out', $batch, $number);
                $batch->lines()->create(['item_id' => $line->item_id, 'qty' => $used, 'cost_base' => $out['cost']]);
                $materials = Num::add($materials, $out['cost']);
            }

            $extra = Num::money(Num::mul($recipe->extra_cost, $qty));
            $total = Num::money(Num::add($materials, $extra));
            $unitCost = Num::round(Num::div($total, $qty), 6);

            Stock::receive($recipe->item_id, (int) $data['to_warehouse_id'], $qty, $unitCost, $date, 'production_in', $batch, [
                'batch_no' => $batch->batch_no,
                'mfg_date' => $date,
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
            ], $number);

            // materials only change shape inside stock; the extra cost is new value added to stock
            if (Num::isPositive($extra)) {
                Ledger::post('production', $date, __('Production :number: :name', ['number' => $number, 'name' => $recipe->item->name]), [
                    ['account' => Account::system('inventory'), 'debit' => $extra],
                    ['account' => Account::system('production_cost'), 'credit' => $extra],
                ], $batch);
            }

            $batch->update(['cost_base' => $total, 'unit_cost' => $unitCost]);

            return $batch;
        });
    }

    public static function cancel(ProductionBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            if ($batch->status === 'cancelled') {
                return;
            }
            Stock::voidSource($batch);
            Ledger::voidSource($batch);
            $batch->update(['status' => 'cancelled']);
        });
    }

    /**
     * What a batch of this size needs and whether the warehouse has it.
     *
     * @return array<int, array{item: Item, needed: string, have: string, enough: bool}>
     */
    public static function needs(Recipe $recipe, mixed $qty, ?int $warehouseId): array
    {
        $rows = [];
        foreach ($recipe->lines as $line) {
            $needed = Num::qty(Num::mul($line->qty, $qty));
            $have = $warehouseId ? Stock::onHand($line->item_id, $warehouseId) : Stock::onHand($line->item_id);
            $rows[] = ['item' => $line->item, 'needed' => $needed, 'have' => $have, 'enough' => Num::cmp($have, $needed) >= 0];
        }

        return $rows;
    }
}
