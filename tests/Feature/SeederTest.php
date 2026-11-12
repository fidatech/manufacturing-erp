<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Asset;
use App\Models\Currency;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JournalLine;
use App\Models\Lookup;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ledger;
use App\Services\Stock;
use Database\Seeders\Clients\ClientSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private function counts(): array
    {
        return [User::count(), Currency::count(), Unit::count(), Account::count(), Lookup::count(), ItemCategory::count(), Warehouse::count(), Role::count()];
    }

    public function test_the_general_seeders_make_the_starting_records_once(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', UserSeeder::EMAIL)->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->post(route('login.store'), ['email' => 'admin@gmail.com', 'password' => 'admin@123'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();

        $this->assertSame(['AFN', 'PKR', 'USD'], Currency::orderBy('code')->pluck('code')->all());
        $this->assertSame('USD', Currency::where('is_base', true)->value('code'));
        $this->assertSame(1, Warehouse::count());
        $this->assertSame(3, Account::ofType('cash')->count());
        $this->assertTrue(Account::ofType('expense')->where('name', 'Rent')->exists());
        $this->assertTrue(Account::ofType('income')->where('name', 'Service income')->exists());
        $this->assertNotNull(Account::system('production_cost'));
        $this->assertTrue(Lookup::where('type', 'asset_category')->where('name', 'Vehicles')->exists());
        $this->assertTrue(ItemCategory::where('type', 'raw')->where('name', 'Packing materials')->exists());
        $finance = Lookup::where('type', 'department')->where('name', 'Finance')->firstOrFail();
        $this->assertTrue(Lookup::where('type', 'designation')->where('parent_id', $finance->id)->where('name', 'Accountant')->exists());

        $accountant = Role::findByName('Accountant', 'web');
        $this->assertTrue($accountant->hasPermissionTo('money.manage'));
        $this->assertFalse($accountant->hasPermissionTo('users.manage'));
        $this->assertTrue(Role::findByName('Viewer', 'web')->hasPermissionTo('sales.view'));
        $this->assertFalse(Role::findByName('Viewer', 'web')->hasPermissionTo('sales.manage'));

        // a second run adds nothing and does not reset a changed password
        $admin->update(['password' => 'changed-by-the-owner']);
        $before = $this->counts();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($before, $this->counts());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('changed-by-the-owner', $admin->fresh()->password));
    }

    public function test_a_client_seeder_loads_opening_data_once_and_the_books_balance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seeder = new class extends ClientSeeder
        {
            protected function client(): string
            {
                return 'example';
            }
        };
        $seeder->run();

        $this->assertSame('Example Factory', \App\Models\Setting::where('key', 'company_name')->value('value'));
        $customer = Account::where('name', 'Example Customer')->firstOrFail();
        $this->assertSame('7000.00', Ledger::balance($customer->id));
        $this->assertSame('100.00', Ledger::baseBalance($customer->id));
        $this->assertSame('-1000.00', Ledger::balance(Account::where('name', 'Example Owner')->value('id')));
        $this->assertSame('250.00', Ledger::balance(Account::where('name', 'Example Bank')->value('id')));
        $this->assertSame('0.00', Ledger::balance(Account::where('name', 'Example Supplier')->value('id')));

        $store = Warehouse::where('name', 'Example Store')->firstOrFail();
        $sugar = Item::where('name', 'Sugar')->firstOrFail();
        $this->assertSame('500.0000', \App\Support\Num::qty(Stock::onHand($sugar->id, $store->id)));
        $this->assertSame('400.00', Ledger::balance(Account::system('inventory')->id));   // 500 kg at 0.80
        $this->assertSame('900.00', \App\Support\Num::money(Asset::where('name', 'Example Mixer')->value('cost_base')));

        $recipe = Recipe::with('lines.item')->where('name', 'Example Syrup 20 kg')->firstOrFail();
        $this->assertSame(2, $recipe->lines->count());
        $this->assertSame('5.2000', $recipe->unitCost());   // 5 kg sugar at 0.80 + 0.1 kg flavour at 12
        $worker = Account::ofType('employee')->where('name', 'Example Worker')->firstOrFail();
        $this->assertSame('8000', (string) $worker->metaValue('salary'));

        $sums = JournalLine::where('is_void', false)->selectRaw('SUM(base_debit) as d, SUM(base_credit) as c')->toBase()->first();
        $this->assertSame(number_format((float) $sums->d, 2, '.', ''), number_format((float) $sums->c, 2, '.', ''));

        // running it again changes nothing
        $lines = JournalLine::count();
        $seeder->run();
        $this->assertSame($lines, JournalLine::count());
        $this->assertSame(1, Account::where('name', 'Example Customer')->count());

        // the screens open with the loaded data
        $this->actingAs(User::where('email', UserSeeder::EMAIL)->firstOrFail());
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('statement', ['id' => $customer->id, 'from' => '2026-01-01']))->assertOk()->assertSee('7,000.00');
        $this->get(route('stock.card', ['id' => $sugar->id, 'from' => '2026-01-01']))->assertOk()->assertSee('500');
    }

    public function test_a_client_seeder_explains_a_missing_data_file(): void
    {
        $seeder = new class extends ClientSeeder
        {
            protected function client(): string
            {
                return 'nobody';
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not part of the repository');
        $seeder->run();
    }
}
