<?php

namespace Database\Seeders\Clients;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Item;
use App\Models\Lookup;
use App\Models\Recipe;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AssetService;
use App\Services\Ledger;
use App\Services\Rates;
use App\Services\StockDocService;
use App\Support\Num;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Loads the real opening data of ONE client from a data file:
 * company details, warehouses, items with opening stock, accounts with opening
 * balances, assets, recipes and employees.
 *
 * The data file holds the client's own figures, so it is not kept in the repository.
 * Put it in database/seeders/Clients/data/ on the machine where the seeder runs.
 *
 * Everything is saved together or not at all, and the seeder refuses to run twice,
 * because opening balances written a second time would double the books.
 */
abstract class ClientSeeder extends Seeder
{
    /** Short name of the client: the data file is database/seeders/Clients/data/{name}.php */
    abstract protected function client(): string;

    protected function dataPath(): string
    {
        return database_path('seeders/Clients/data/'.$this->client().'.php');
    }

    public function run(): void
    {
        $path = $this->dataPath();
        if (! is_file($path)) {
            throw new RuntimeException("The data file of this client is missing: {$path}\nIt is not part of the repository. Copy it there and run the seeder again.");
        }
        $flag = 'seed.client.'.$this->client();
        if (Setting::where('key', $flag)->exists()) {
            $this->command?->warn('The data of "'.$this->client().'" was loaded before. Nothing was changed.');

            return;
        }

        $data = require $path;

        DB::transaction(function () use ($data, $path, $flag) {
            $date = $data['opening_date'];

            $this->settings($data['settings'] ?? [], $data['logo'] ?? null, dirname($path));
            foreach ($data['rates'] ?? [] as $rate) {
                ExchangeRate::firstOrCreate(['currency_id' => $this->currency($rate['currency']), 'date' => $rate['date']], ['rate' => $rate['rate']]);
            }
            Rates::forget();

            $warehouses = [];
            foreach ($data['warehouses'] ?? [] as $row) {
                $warehouses[$row['name']] = Warehouse::firstOrCreate(['name' => $row['name']], ['location' => $row['location'] ?? null, 'is_active' => true])->id;
            }

            $items = $this->items($data['items'] ?? []);
            $this->stock($data['stock'] ?? [], $items, $warehouses, $date);
            $this->accounts($data['accounts'] ?? [], $date);
            $this->assets($data['assets'] ?? [], $date);
            $this->recipes($data['recipes'] ?? []);
            $this->employees($data['employees'] ?? []);

            Setting::create(['key' => $flag, 'value' => now()->toDateTimeString()]);
        });

        app()->forgetInstance('erp.settings');
        $this->command?->info('Loaded the data of "'.$this->client().'".');
    }

    private function currency(string $code): int
    {
        return (int) (Currency::where('code', $code)->value('id') ?? throw new RuntimeException("Currency {$code} does not exist. Run the general seeders first: php artisan db:seed"));
    }

    private function unit(string $name): int
    {
        return Unit::firstOrCreate(['name' => $name], ['symbol' => strtolower($name)])->id;
    }

    private function settings(array $settings, ?string $logo, string $folder): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        if ($logo && is_file($folder.'/'.$logo)) {
            $target = 'company/'.$this->client().'-logo.'.pathinfo($logo, PATHINFO_EXTENSION);
            Storage::disk('local')->put($target, file_get_contents($folder.'/'.$logo));
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $target]);
        }
    }

    /** @return array<string, int> key of the data file => item id */
    private function items(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $item = Item::create([
                'type' => $row['type'],
                'code' => $row['code'] ?? null,
                'name' => $row['name'],
                'unit_id' => $this->unit($row['unit']),
                'packing' => $row['packing'] ?? null,
                'notes' => $row['notes'] ?? null,
                'is_active' => true,
            ]);
            $ids[$row['key']] = $item->id;
        }

        return $ids;
    }

    /** Opening stock: one paper for each warehouse. Cost is 0 where the client's file gives none. */
    private function stock(array $rows, array $items, array $warehouses, string $date): void
    {
        $byWarehouse = [];
        foreach ($rows as $row) {
            $byWarehouse[$row['warehouse']][] = [
                'item_id' => $items[$row['item']] ?? throw new RuntimeException('Unknown item in the stock list: '.$row['item']),
                'qty' => $row['qty'],
                'unit_cost' => $row['unit_cost'] ?? 0,
                'batch_no' => $row['batch_no'] ?? '',
                'mfg_date' => $row['mfg_date'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
            ];
        }
        foreach ($byWarehouse as $name => $lines) {
            StockDocService::create([
                'type' => 'opening',
                'date' => $date,
                'warehouse_id' => $warehouses[$name] ?? throw new RuntimeException('Unknown warehouse in the stock list: '.$name),
                'notes' => 'Opening stock from the client\'s stock book',
                'lines' => $lines,
            ]);
        }
    }

    private function accounts(array $rows, string $date): void
    {
        $opening = Account::system('opening');

        foreach ($rows as $row) {
            $account = Account::create([
                'account_type_id' => AccountType::where('key', $row['type'])->value('id') ?? throw new RuntimeException('Unknown kind of account: '.$row['type']),
                'name' => $row['name'],
                'currency_id' => $this->currency($row['currency']),
                'phone' => $row['phone'] ?? null,
                'company' => $row['company'] ?? null,
                'address' => $row['address'] ?? null,
                'meta' => $row['meta'] ?? null,
                'is_active' => true,
            ]);

            $amount = Num::money($row['opening'] ?? 0);
            if (! Num::isPositive($amount)) {
                continue;
            }
            $side = $row['side'];
            $base = Rates::toBase($amount, $account->currency_id, $date);
            Ledger::post('opening', $date, 'Opening balance: '.$account->name, [
                ['account' => $account, $side => $amount, 'base' => $base],
                ['account' => $opening, ($side === 'debit' ? 'credit' : 'debit') => $base],
            ], $account);
        }
    }

    /** Things the company already owned. They do not lose value until a life in months is set on the asset. */
    private function assets(array $rows, string $date): void
    {
        foreach ($rows as $row) {
            AssetService::create([
                'name' => $row['name'],
                'class' => 'fixed',
                'kind' => $row['kind'] ?? 'tangible',
                'category' => $row['category'] ?? null,
                'purchase_date' => $row['date'] ?? $date,
                'cost' => $row['cost'],
                'currency_id' => $this->currency($row['currency']),
                'life_months' => $row['life_months'] ?? 0,
            ]);
        }
    }

    /**
     * Recipes. An ingredient is matched to a raw material of exactly the same name;
     * when there is none, it is added as a new raw material in kilograms with the cost the recipe gives.
     */
    private function recipes(array $rows): void
    {
        foreach ($rows as $row) {
            $product = Item::where('type', 'product')->where('name', $row['product'])->first()
                ?? throw new RuntimeException('The product of a recipe does not exist: '.$row['product']);

            $recipe = Recipe::create([
                'item_id' => $product->id,
                'name' => $row['name'],
                'method' => $row['method'],
                'pack_weight' => $row['pack_weight'],
                'extra_cost' => $row['extra_cost'] ?? 0,
                'is_active' => true,
            ]);

            foreach ($row['lines'] as $line) {
                $name = trim(preg_replace('/\s+/', ' ', $line['item']));
                $raw = Item::where('type', 'raw')->where('name', $name)->first()
                    ?? Item::create(['type' => 'raw', 'name' => $name, 'unit_id' => $this->unit($line['unit'] ?? 'Kilogram'), 'avg_cost' => $line['cost'] ?? 0, 'is_active' => true]);
                $recipe->lines()->create(['item_id' => $raw->id, 'qty' => $line['qty']]);
            }
        }
    }

    private function employees(array $data): void
    {
        if (empty($data['list'])) {
            return;
        }
        $typeId = AccountType::where('key', 'employee')->value('id');
        $currencyId = $this->currency($data['currency']);

        foreach ($data['list'] as $row) {
            if (! empty($row['job_title'])) {
                Lookup::firstOrCreate(['type' => 'designation', 'parent_id' => null, 'name' => $row['job_title']]);
            }
            Account::create([
                'account_type_id' => $typeId,
                'name' => $row['name'],
                'currency_id' => $currencyId,
                'phone' => $row['phone'] ?? null,
                'meta' => array_filter([
                    'father_name' => $row['father_name'] ?? null,
                    'job_title' => $row['job_title'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'salary' => $row['salary'] ?? null,
                    'salary_tax' => $row['salary_tax'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
                'is_active' => true,
            ]);
        }
    }
}
