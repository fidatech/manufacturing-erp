<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Item;
use App\Models\LabTest;
use App\Models\Lookup;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Ledger;
use App\Services\Stock;
use App\Support\Num;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $raw;

    private Warehouse $finished;

    private Item $anar;

    private Item $acid;

    private Item $benzoate;

    private Item $can;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        $this->raw = Warehouse::create(['name' => 'Raw materials']);
        $this->finished = Warehouse::create(['name' => 'Final products']);
        $kg = Unit::where('name', 'Kilogram')->value('id');
        $canUnit = Unit::where('name', 'Can')->value('id');
        $this->anar = Item::create(['type' => 'product', 'name' => 'Anar Concentrate', 'code' => 'KC786', 'unit_id' => $canUnit, 'packing' => 'Can 20 kg', 'sale_price' => '45']);
        $this->acid = Item::create(['type' => 'raw', 'name' => 'Citric Acid', 'unit_id' => $kg]);
        $this->benzoate = Item::create(['type' => 'raw', 'name' => 'Sodium Benzoate', 'unit_id' => $kg]);
        $this->can = Item::create(['type' => 'raw', 'name' => 'Empty can', 'unit_id' => $canUnit]);

        // raw materials already in the warehouse: 300 kg acid at 1.75, 2 kg benzoate at 6, 60 cans at 3
        $this->post(route('stock-docs.store', ['type' => 'opening']), ['date' => '2026-01-01', 'warehouse_id' => $this->raw->id, 'lines' => [
            ['item_id' => $this->acid->id, 'qty' => '300', 'unit_cost' => '1.75'],
            ['item_id' => $this->benzoate->id, 'qty' => '2', 'unit_cost' => '6'],
            ['item_id' => $this->can->id, 'qty' => '60', 'unit_cost' => '3'],
        ]])->assertRedirect();
    }

    private function recipe(): Recipe
    {
        $this->post(route('recipes.store'), [
            'item_id' => $this->anar->id, 'method' => 'liquid', 'pack_weight' => '20', 'extra_cost' => '1', 'shelf_life_months' => '24', 'is_active' => '1',
            'lines' => [
                ['item_id' => $this->acid->id, 'qty' => '5.5'],
                ['item_id' => $this->benzoate->id, 'qty' => '0.02'],
                ['item_id' => $this->can->id, 'qty' => '1'],
                ['item_id' => '', 'qty' => ''],
            ],
        ])->assertRedirect(route('recipes.index'));

        return Recipe::with('lines.item.unit')->firstOrFail();
    }

    private function produce(Recipe $recipe, string $qty, array $extra = [])
    {
        return $this->post(route('production.store'), $extra + [
            'recipe_id' => $recipe->id, 'qty' => $qty, 'date' => '2026-04-10', 'warehouse_id' => $this->raw->id, 'to_warehouse_id' => $this->finished->id,
        ]);
    }

    public function test_a_recipe_knows_its_cost_and_its_water(): void
    {
        $this->get(route('recipes.index'))->assertOk();
        $this->get(route('recipes.create'))->assertOk();
        $recipe = $this->recipe();

        $this->assertSame(3, $recipe->lines->count());
        // 5.5 x 1.75 + 0.02 x 6 + 1 x 3 + 1 other cost
        $this->assertSame('13.7450', $recipe->unitCost());
        // 20 kg can less 5.52 kg of weighed ingredients; the can itself is not weighed
        $this->assertSame('14.4800', $recipe->water());

        $this->get(route('recipes.index'))->assertOk()->assertSee('Anar Concentrate')->assertSee('13.75');
        $this->get(route('recipes.edit', ['id' => $recipe->id]))->assertOk()->assertSee('Citric Acid');

        // a recipe needs ingredients
        $this->post(route('recipes.store'), ['item_id' => $this->anar->id, 'method' => 'powder', 'lines' => [['item_id' => '', 'qty' => '']]])->assertSessionHas('danger');
    }

    public function test_production_uses_raw_materials_and_makes_the_product(): void
    {
        $recipe = $this->recipe();

        $this->get(route('production.create'))->assertOk();
        $this->get(route('production.create', ['recipe' => $recipe->id, 'qty' => 50, 'warehouse' => $this->raw->id]))->assertOk()->assertSee('275')->assertSee('724');   // 724 kg of water

        $this->produce($recipe, '50')->assertRedirect();
        $batch = ProductionBatch::firstOrFail();

        $this->assertSame('25.0000', Stock::onHand($this->acid->id));        // 300 - 275
        $this->assertSame('1.0000', Stock::onHand($this->benzoate->id));     // 2 - 1
        $this->assertSame('10.0000', Stock::onHand($this->can->id));         // 60 - 50
        $this->assertSame('50.0000', Stock::onHand($this->anar->id, $this->finished->id, $batch->batch_no));
        $this->assertSame('687.25', Num::money($batch->cost_base));          // 481.25 + 6 + 150 materials, + 50 other cost
        $this->assertSame('13.745000', Num::round($this->anar->fresh()->avg_cost, 6));
        $this->assertSame('2028-04-10', $batch->expiry_date->format('Y-m-d'));  // 24 months
        $this->assertSame('767.00', Ledger::balance(Account::system('inventory')->id));        // 717 opening + 50 added
        $this->assertSame('-50.00', Ledger::balance(Account::system('production_cost')->id));

        $this->get(route('production.index'))->assertOk()->assertSee('Anar Concentrate');
        $this->get(route('production.show', ['id' => $batch->id]))->assertOk()->assertSee('Citric Acid')->assertSee('687.25');
        $this->get(route('production.report', ['from' => '2026-04-01', 'to' => '2026-04-30']))->assertOk()->assertSee('Anar Concentrate')->assertSee('687.25');
        $this->get(route('stock.card', ['id' => $this->anar->id]))->assertOk()->assertSee('Produced');
    }

    public function test_production_stops_when_a_raw_material_is_missing(): void
    {
        $recipe = $this->recipe();

        // 61 cans need 61 empty cans but there are 60
        $this->produce($recipe, '61')->assertSessionHas('danger');

        $this->assertDatabaseCount('production_batches', 0);
        $this->assertSame('300.0000', Stock::onHand($this->acid->id));
        $this->assertSame('0.0000', Stock::onHand($this->anar->id));
    }

    public function test_the_quantity_really_used_can_differ_from_the_recipe(): void
    {
        $recipe = $this->recipe();

        $this->produce($recipe, '10', ['used' => [$this->acid->id => '56', $this->benzoate->id => '0.2', $this->can->id => '10']])->assertRedirect();

        $this->assertSame('244.0000', Stock::onHand($this->acid->id));
        $this->assertSame('139.20', Num::money(ProductionBatch::first()->cost_base));   // 56 x 1.75 + 0.2 x 6 + 30 + 10 other cost
    }

    public function test_a_batch_that_is_partly_sold_cannot_be_cancelled(): void
    {
        $recipe = $this->recipe();
        $this->produce($recipe, '10')->assertRedirect();
        $batch = ProductionBatch::firstOrFail();
        $customer = $this->account('customer', 'Zaki', 'USD');

        $this->post(route('sales.store'), ['type' => 'invoice', 'date' => '2026-04-12', 'customer_id' => $customer->id, 'warehouse_id' => $this->finished->id,
            'lines' => [['item_id' => $this->anar->id, 'qty' => '4', 'price' => '45']]])->assertRedirect();
        $this->assertSame('54.98', Ledger::balance(Account::system('cogs')->id));   // 4 x 13.745

        $this->post(route('production.cancel', ['id' => $batch->id]))->assertSessionHas('danger');
        $this->assertSame('posted', $batch->fresh()->status);

        // a second batch nobody bought from can be cancelled: everything goes back
        $this->produce($recipe, '5')->assertRedirect();
        $second = ProductionBatch::orderByDesc('id')->firstOrFail();
        $acidBefore = Stock::onHand($this->acid->id);
        $this->post(route('production.cancel', ['id' => $second->id]))->assertSessionHas('success');
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame(Num::qty(Num::add($acidBefore, '27.5')), Stock::onHand($this->acid->id));
    }

    public function test_lab_tests_decide_the_quality_status_of_a_batch(): void
    {
        foreach (['Brix', 'pH', 'Colour'] as $name) {
            $this->post(route('lookups.store', ['type' => 'qc_parameter']), ['name' => $name])->assertRedirect();
        }
        $this->assertSame(3, Lookup::where('type', 'qc_parameter')->count());

        $recipe = $this->recipe();
        $this->produce($recipe, '10')->assertRedirect();
        $batch = ProductionBatch::firstOrFail();
        $this->assertSame('pending', $batch->qc_status);

        $this->get(route('lab.index'))->assertOk()->assertSee('Batches waiting for a test')->assertSee($batch->batch_no);
        $this->get(route('lab.create', ['batch' => $batch->id]))->assertOk()->assertSee('Brix')->assertSee('Colour');
        $this->get(route('lab.create', ['subject' => 'raw']))->assertOk()->assertSee('Citric Acid');

        // one thing is not right: the test fails and so does the batch
        $this->post(route('lab.store'), ['subject' => 'product', 'date' => '2026-04-10', 'production_batch_id' => $batch->id, 'lines' => [
            ['parameter' => 'Brix', 'standard' => '60-62', 'result' => '61', 'ok' => '1'],
            ['parameter' => 'pH', 'standard' => '2.5-3', 'result' => '3.6', 'ok' => '0'],
        ]])->assertRedirect()->assertSessionHas('warning');
        $this->assertSame('failed', $batch->fresh()->qc_status);
        $this->get(route('production.index', ['qc' => 'failed']))->assertOk()->assertSee($batch->batch_no);
        $this->get(route('dashboard'))->assertOk()->assertSee('Batches for the laboratory');

        // tested again after correction: passed
        $this->post(route('lab.store'), ['subject' => 'product', 'date' => '2026-04-11', 'production_batch_id' => $batch->id, 'lines' => [
            ['parameter' => 'Brix', 'standard' => '60-62', 'result' => '61', 'ok' => '1'],
            ['parameter' => 'pH', 'standard' => '2.5-3', 'result' => '2.8', 'ok' => '1'],
        ]])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('passed', $batch->fresh()->qc_status);

        $test = LabTest::orderByDesc('id')->firstOrFail();
        $this->get(route('lab.show', ['id' => $test->id]))->assertOk()->assertSee('2.8');
        $this->get(route('production.show', ['id' => $batch->id]))->assertOk()->assertSee($test->number);

        // deleting the newest test brings back the older result
        $this->delete(route('lab.destroy', ['id' => $test->id]))->assertRedirect(route('lab.index'));
        $this->assertSame('failed', $batch->fresh()->qc_status);

        // a raw material that arrived can be tested too
        $this->post(route('lab.store'), ['subject' => 'raw', 'date' => '2026-04-12', 'item_id' => $this->acid->id, 'batch_no' => 'CA-25', 'lines' => [
            ['parameter' => 'Colour', 'standard' => 'White', 'result' => 'White', 'ok' => '1'],
        ]])->assertRedirect();
        $this->assertSame('raw', LabTest::orderByDesc('id')->first()->subject);
        $this->get(route('lab.index', ['status' => 'passed']))->assertOk()->assertSee('CA-25');
    }

    public function test_production_and_lab_need_their_own_permissions(): void
    {
        $this->userWith(['sales.view']);

        $this->get(route('recipes.index'))->assertForbidden();
        $this->get(route('production.index'))->assertForbidden();
        $this->get(route('lab.index'))->assertForbidden();
    }
}
