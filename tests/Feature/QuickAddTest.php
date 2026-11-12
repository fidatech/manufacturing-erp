<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Lookup;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "+ New" beside a pick list: the add form opens in a pop-up and the page is not left. */
class QuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_pick_lists_offer_a_pop_up_to_add_a_new_record(): void
    {
        $this->admin();

        $supplierForm = route('accounts.create', ['type' => 'supplier']);
        $this->get(route('purchases.create', ['type' => 'order']))->assertOk()
            ->assertSee('data-quick-add="'.e($supplierForm).'"', false)
            ->assertSee('data-quick-add="'.e(route('warehouses.create')).'"', false)
            ->assertSee('data-quick-target="select[data-item]"', false)
            ->assertSee('New raw material');
        $this->get(route('sales.create', ['type' => 'invoice']))->assertOk()->assertSee('data-quick-add="'.e(route('accounts.create', ['type' => 'customer'])).'"', false);
        $this->get(route('money.create', ['kind' => 'expense']))->assertOk()->assertSee('data-quick-add="'.e(route('accounts.create', ['type' => 'expense'])).'"', false);
        $this->get(route('items.create', ['type' => 'raw']))->assertOk()->assertSee('data-quick-add="'.e(route('units.create')).'"', false);

        // the pop-up gets only the form, without the menu
        $this->get($supplierForm.'?modal=1')->assertOk()->assertSee('data-quick-form', false)->assertSee('name="_quick"', false)->assertDontSee('app-sidebar', false);
        $this->get(route('warehouses.create', ['modal' => 1]))->assertOk()->assertSee('data-quick-form', false)->assertDontSee('app-sidebar', false);
    }

    public function test_a_record_saved_from_the_pop_up_comes_back_as_data(): void
    {
        $this->admin();
        $usd = $this->currency('USD')->id;

        $answer = $this->postJson(route('accounts.store', ['type' => 'supplier']), ['_quick' => 1, 'name' => 'Quick Supplier', 'is_active' => '1', 'currencies' => [$usd => ['on' => '1']]])->assertOk()->json();
        $supplier = Account::where('name', 'Quick Supplier')->firstOrFail();
        $this->assertSame([$supplier->id], $answer['ids']);
        // the page that asked reads itself again and finds the new supplier in its list
        $this->get(route('purchases.create', ['type' => 'order']))->assertSee('Quick Supplier');

        $answer = $this->postJson(route('warehouses.store'), ['_quick' => 1, 'name' => 'Quick Store', 'is_active' => '1'])->assertOk()->json();
        $this->assertSame([Warehouse::where('name', 'Quick Store')->value('id')], $answer['ids']);

        // lists that keep the name as their value get the name back too
        $answer = $this->postJson(route('lookups.store', ['type' => 'asset_category']), ['_quick' => 1, 'name' => 'Quick Category'])->assertOk()->json();
        $this->assertSame(['Quick Category'], $answer['names']);
        $this->assertTrue(Lookup::where('type', 'asset_category')->where('name', 'Quick Category')->exists());

        // what is wrong comes back as messages for the pop-up, and nothing is saved
        $this->postJson(route('warehouses.store'), ['_quick' => 1, 'name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $afn = $this->currency('AFN')->id;
        $this->postJson(route('accounts.store', ['type' => 'supplier']), ['_quick' => 1, 'name' => 'No Rate', 'is_active' => '1', 'currencies' => [$afn => ['on' => '1', 'opening' => '500', 'side' => 'credit']]])
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertFalse(Account::where('name', 'No Rate')->exists());

        // without the pop-up mark the form still goes back to its list, as before
        $this->post(route('warehouses.store'), ['name' => 'Usual Store', 'is_active' => '1'])->assertRedirect(route('warehouses.index'));
    }

    public function test_the_pop_up_is_offered_only_to_those_who_may_add(): void
    {
        $this->userWith(['purchases.manage', 'purchases.view']);

        $this->get(route('purchases.create', ['type' => 'order']))->assertOk()->assertDontSee('data-quick-add', false);
        $this->get(route('accounts.create', ['type' => 'supplier', 'modal' => 1]))->assertForbidden();
    }
}
