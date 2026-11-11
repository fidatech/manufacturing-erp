<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Unit;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_system_starts_with_currencies_cash_boxes_and_units_but_no_users(): void
    {
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'is_base' => true]);
        $this->assertDatabaseHas('currencies', ['code' => 'AFN']);
        $this->assertDatabaseHas('accounts', ['name' => 'Cash AFN']);
        $this->assertDatabaseHas('accounts', ['system_key' => 'sales']);
        $this->assertDatabaseHas('units', ['name' => 'Kilogram']);
    }

    public function test_an_administrator_can_open_every_page_of_the_menu(): void
    {
        $admin = $this->admin();
        $opened = 0;

        foreach (Menu::for($admin) as $item) {
            foreach ($item['children'] ?? [$item] as $entry) {
                if (isset($entry['route'])) {
                    $this->get(Menu::url($entry))->assertOk();
                    $opened++;
                }
            }
        }

        $this->assertGreaterThan(20, $opened);
    }

    public function test_every_list_has_a_working_add_form(): void
    {
        $this->admin();

        foreach (['units', 'warehouses', 'currencies', 'rates', 'account-types', 'item-categories', 'users', 'roles'] as $name) {
            $this->get(route("$name.create"))->assertOk();
        }
        foreach (['customer', 'supplier', 'bank', 'sarafi', 'cash', 'shareholder', 'employee', 'warehouse_manager', 'liability', 'expense', 'income'] as $type) {
            $this->get(route('accounts.create', ['type' => $type]))->assertOk();
        }
        foreach (['product', 'raw'] as $type) {
            $this->get(route('items.create', ['type' => $type]))->assertOk();
        }
        $this->get(route('journal.create'))->assertOk();
        $this->get(route('money.profit'))->assertOk();
    }

    public function test_a_unit_can_be_added_changed_and_deleted_and_it_is_logged(): void
    {
        $this->admin();

        $this->post(route('units.store'), ['name' => 'Box', 'symbol' => 'bx'])->assertRedirect(route('units.index'));
        $unit = Unit::where('name', 'Box')->firstOrFail();

        $this->get(route('units.edit', ['id' => $unit->id]))->assertOk()->assertSee('Box');
        $this->put(route('units.update', ['id' => $unit->id]), ['name' => 'Big box', 'symbol' => 'bx'])->assertRedirect(route('units.index'));
        $this->assertSame('Big box', $unit->fresh()->name);

        $this->delete(route('units.destroy', ['id' => $unit->id]))->assertRedirect(route('units.index'));
        $this->assertNull($unit->fresh());

        $this->assertSame(['created', 'updated', 'deleted'], AuditLog::where('subject_type', 'Unit')->orderBy('id')->pluck('action')->all());
        $this->get(route('audit.index'))->assertOk()->assertSee('Big box');
    }

    public function test_a_role_decides_what_a_user_may_do(): void
    {
        $this->userWith(['setup.view']);

        $this->get(route('units.index'))->assertOk();
        $this->get(route('units.create'))->assertForbidden();
        $this->post(route('units.store'), ['name' => 'Box'])->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('money.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('users.index'));
    }

    public function test_roles_and_users_can_be_managed(): void
    {
        $this->admin();

        $this->post(route('roles.store'), ['name' => 'Storekeeper', 'permissions' => ['inventory.view', 'inventory.manage']])->assertRedirect(route('roles.index'));
        $role = Role::where('name', 'Storekeeper')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('inventory.manage'));

        $this->post(route('users.store'), [
            'name' => 'Store Keeper', 'email' => 'store@example.com', 'password' => 'a-long-password',
            'role' => 'Storekeeper', 'calendar' => 'shamsi', 'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'store@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('Storekeeper'));
        $this->assertTrue($user->can('inventory.manage'));
        $this->assertFalse($user->can('money.view'));
        $this->assertSame('shamsi', $user->calendar);

        // the Administrator role cannot be edited
        $this->get(route('roles.edit', ['id' => Role::where('name', User::ADMIN_ROLE)->value('id')]))->assertForbidden();
    }

    public function test_a_switched_off_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'correct-password', 'is_active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'correct-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dates_follow_the_calendar_the_user_chose(): void
    {
        $admin = $this->admin(['calendar' => 'shamsi']);

        // a Solar Hijri date typed by the user is stored as Gregorian
        $this->post(route('rates.store'), [
            'currency_id' => $this->currency('AFN')->id, 'date' => '1405/08/05', 'rate' => '70.5', '_shamsi' => ['date'],
        ])->assertRedirect(route('rates.index'));
        $this->assertDatabaseHas('exchange_rates', ['currency_id' => $this->currency('AFN')->id, 'rate' => 70.5]);
        $this->assertSame('2026-10-27', \App\Models\ExchangeRate::first()->date->format('Y-m-d'));

        // and shown back in the user's calendar
        $this->get(route('rates.index'))->assertOk()->assertSee('1405/08/05');

        // a day that does not exist is refused
        $this->post(route('rates.store'), [
            'currency_id' => $this->currency('PKR')->id, 'date' => '1405/07/31', 'rate' => '280', '_shamsi' => ['date'],
        ])->assertSessionHasErrors('date');

        // one click switches to Gregorian
        $this->post(route('preferences.calendar'))->assertRedirect();
        $this->assertSame('gregorian', $admin->fresh()->calendar);
        $this->get(route('rates.index'))->assertSee('27/10/2026');
    }
}
