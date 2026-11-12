<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Excel and PDF of every list and report, and the extra filters of the lists. */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    /** The text of the sheet inside a downloaded Excel file. */
    private function sheet(TestResponse $response): string
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
        $this->assertNotFalse(simplexml_load_string($xml), 'The sheet is not valid XML.');

        return $xml;
    }

    public function test_a_list_downloads_as_excel_with_every_row_and_without_the_buttons(): void
    {
        $this->admin();
        foreach (range(1, 30) as $number) {
            Warehouse::create(['name' => sprintf('Store %02d & Co', $number), 'is_active' => true]);
        }

        // the screen shows 25 of them; the file has all 30
        $this->get(route('warehouses.index'))->assertOk()->assertSee('Store 25 &amp; Co', false)->assertDontSee('Store 26');
        $xml = $this->sheet($this->get(route('warehouses.index', ['export' => 'xlsx'])));
        $this->assertStringContainsString('Store 01 &amp; Co', $xml);
        $this->assertStringContainsString('Store 30 &amp; Co', $xml);
        $this->assertStringContainsString('Warehouses', $xml);
        $this->assertStringNotContainsString('Actions', $xml);
        $this->assertStringNotContainsString('Edit', $xml);

        // the filters of the screen apply to the file
        $xml = $this->sheet($this->get(route('warehouses.index', ['export' => 'xlsx', 'q' => 'Store 07'])));
        $this->assertStringContainsString('Store 07', $xml);
        $this->assertStringNotContainsString('Store 08', $xml);

        $csv = $this->get(route('warehouses.index', ['export' => 'csv']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Store 30 & Co"', $csv);

        // a form is not a list: it stays a page
        $this->get(route('purchases.create', ['type' => 'order', 'export' => 'xlsx']))->assertOk()->assertSee('<!DOCTYPE html>', false);
    }

    public function test_a_report_downloads_as_excel_with_real_numbers(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');
        $this->post(route('money.store', ['kind' => 'out']), ['date' => today_iso(), 'money_id' => $this->cash('AFN')->id, 'other_id' => $customer->id, 'amount' => '7000']);

        $xml = $this->sheet($this->get(route('reports.trial', ['export' => 'xlsx'])));
        $this->assertStringContainsString('Haji Sharif', $xml);
        $this->assertStringContainsString('Trial balance', $xml);
        $this->assertMatchesRegularExpression('/<c r="[A-Z]+\d+" s="[23]"><v>(100|7000)\.00<\/v><\/c>/', $xml);   // amounts are numbers, not text

        $xml = $this->sheet($this->get(route('statement', ['id' => $customer->id, 'export' => 'xlsx'])));
        $this->assertStringContainsString('<v>7000.00</v>', $xml);
    }

    public function test_every_list_opens_as_a_paper_and_pdf_starts_the_print_window(): void
    {
        $this->admin();
        Warehouse::create(['name' => 'Main Store', 'is_active' => true]);

        $this->get(route('warehouses.index', ['print' => 1]))->assertOk()
            ->assertSee('paper-head', false)->assertSee('Main Store')->assertSee('Warehouses')->assertDontSee('app-sidebar', false)->assertDontSee('window.print(); }, 300)', false);
        $this->get(route('warehouses.index', ['print' => 1, 'pdf' => 1]))->assertOk()->assertSee('paper-head', false)->assertSee('window.print(); }, 300)', false);
        foreach (['money.index', 'journal.index', 'exchange.index', 'chart', 'payroll.index'] as $list) {
            $this->get(route($list, ['print' => 1, 'pdf' => 1]))->assertOk()->assertSee('paper-head', false)->assertDontSee('app-sidebar', false);
        }
        $this->get(route('sales.index', ['type' => 'invoice', 'print' => 1]))->assertOk()->assertSee('paper-head', false);
        $this->get(route('accounts.index', ['type' => 'customer', 'print' => 1]))->assertOk()->assertSee('paper-head', false);
    }

    public function test_lists_have_filters_where_they_help(): void
    {
        $this->admin();
        $kg = Unit::firstOrFail();
        $flavours = ItemCategory::create(['name' => 'Flavours', 'type' => 'raw']);
        $colours = ItemCategory::create(['name' => 'Colours', 'type' => 'raw']);
        Item::create(['type' => 'raw', 'name' => 'Mango Flavour', 'unit_id' => $kg->id, 'item_category_id' => $flavours->id, 'is_active' => true]);
        Item::create(['type' => 'raw', 'name' => 'Sunset Yellow', 'unit_id' => $kg->id, 'item_category_id' => $colours->id, 'is_active' => false]);

        $items = fn (array $query) => $this->get(route('items.index', ['type' => 'raw'] + $query))->assertOk();
        $items([])->assertSee('All groups')->assertSee('Active and not active')->assertSee('Mango Flavour')->assertSee('Sunset Yellow');
        $items(['group' => $flavours->id])->assertSee('Mango Flavour')->assertDontSee('Sunset Yellow');
        $items(['active' => 'no'])->assertDontSee('Mango Flavour')->assertSee('Sunset Yellow');

        $this->rate('AFN', '70', '2025-05-01');
        $this->rate('PKR', '280', '2026-05-01');
        $rates = fn (array $query) => $this->get(route('rates.index', $query))->assertOk();
        $rates(['currency' => $this->currency('AFN')->id])->assertSee('1 USD = 70 AFN')->assertDontSee('1 USD = 280 PKR');
        $rates(['year' => 2026])->assertSee('1 USD = 280 PKR')->assertDontSee('1 USD = 70 AFN');

        $this->get(route('accounts.index', ['type' => 'customer', 'active' => 'no']))->assertOk()->assertSee('Nothing found');
        $this->get(route('stock-docs.index', ['type' => 'transfer', 'from' => '2026-01-01', 'to' => '2026-12-31']))->assertOk()->assertSee('name="warehouse"', false);
        $this->get(route('hr.index', ['screen' => 'leave', 'employee' => 999]))->assertOk()->assertSee('All employees');
    }
}
