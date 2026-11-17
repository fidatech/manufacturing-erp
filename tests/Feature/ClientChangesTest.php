<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Changes asked by the client after the first look at the system (November 2026). */
class ClientChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_can_be_filtered_by_category_currency_and_dates(): void
    {
        $this->admin();
        $this->rate('AFN', '70');
        $rent = $this->account('expense', 'Rent', 'USD');
        $fuel = $this->account('expense', 'Fuel', 'USD');
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-05', 'money_id' => $this->cash('USD')->id, 'other_id' => $rent->id, 'amount' => '300', 'description' => 'October rent']);
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-10-20', 'money_id' => $this->cash('AFN')->id, 'other_id' => $fuel->id, 'amount' => '7000', 'description' => 'Generator diesel']);
        $this->post(route('money.store', ['kind' => 'expense']), ['date' => '2026-11-02', 'money_id' => $this->cash('USD')->id, 'other_id' => $rent->id, 'amount' => '300', 'description' => 'November rent']);

        $list = fn (array $query) => $this->get(route('money.index', ['type' => 'expense'] + $query))->assertOk();
        $list([])->assertSee('All categories')->assertSee('All currencies')->assertSee('October rent')->assertSee('Generator diesel')->assertSee('700.00 USD');
        $list(['category' => $rent->id])->assertSee('October rent')->assertSee('November rent')->assertDontSee('Generator diesel')->assertSee('600.00 USD');
        $list(['currency' => $this->currency('AFN')->id])->assertSee('Generator diesel')->assertDontSee('October rent')->assertSee('100.00 USD');
        $list(['from' => '2026-10-01', 'to' => '2026-10-31'])->assertSee('October rent')->assertSee('Generator diesel')->assertDontSee('November rent');
    }

    public function test_employee_documents_are_kept_inside_human_resources(): void
    {
        Storage::fake('local');
        $this->userWith(['hr.view', 'hr.manage']);
        $wahab = $this->account('employee', 'Abdul Wahab', 'AFN');
        $khalid = $this->account('employee', 'Khalid Khan', 'AFN');

        $this->get(route('hr.documents.index'))->assertOk()->assertSee('Employee Documents')->assertSee('Abdul Wahab');
        $this->post(route('hr.documents.store'), ['employee_id' => $wahab->id, 'title' => 'Tazkira copy', 'file' => UploadedFile::fake()->create('tazkira.pdf', 120, 'application/pdf')])
            ->assertRedirect(route('hr.documents.index', ['employee' => $wahab->id]));
        $this->post(route('hr.documents.store'), ['employee_id' => $khalid->id, 'title' => 'Contract', 'file' => UploadedFile::fake()->create('contract.pdf', 80, 'application/pdf')]);

        $file = Attachment::where('title', 'Tazkira copy')->firstOrFail();
        $this->assertSame(['Account', $wahab->id], [$file->subject_type, (int) $file->subject_id]);
        $this->get(route('hr.documents.index', ['employee' => $wahab->id]))->assertSee('Tazkira copy')->assertDontSee('>Contract<', false);
        $this->get(route('hr.documents.show', ['id' => $file->id]))->assertOk();

        // a file of the company that is not an employee's is out of reach here
        $other = Attachment::create(['title' => 'Factory licence', 'path' => 'documents/x.pdf', 'original_name' => 'x.pdf', 'size' => 1]);
        $this->get(route('hr.documents.show', ['id' => $other->id]))->assertNotFound();
        $this->post(route('hr.documents.store'), ['employee_id' => $this->cash('USD')->id, 'title' => 'x', 'file' => UploadedFile::fake()->create('x.pdf', 1)])->assertNotFound();

        $this->delete(route('hr.documents.destroy', ['id' => $file->id]))->assertRedirect();
        $this->assertNull(Attachment::find($file->id));

        // looking only: no upload form
        $this->userWith(['hr.view']);
        $this->get(route('hr.documents.index'))->assertOk()->assertSee('Contract')->assertDontSee('Save file');
    }

    public function test_money_in_can_take_several_currencies_with_their_own_rates(): void
    {
        $this->admin();
        $this->rate('AFN', '70', today_iso());
        $customer = $this->account('customer', 'Haji Sharif', 'AFN');

        $this->get(route('money.create', ['kind' => 'in']))->assertOk()->assertSee('data-money-lines', false)->assertSee('Add another currency');

        // 100 dollars at 71 afghani each, and 3,000 afghani in cash: 10,100 afghani off his account
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '100', 'rate' => '71', 'operation' => 'multiply'],
            ['money_id' => $this->cash('AFN')->id, 'amount' => '3,000'],
            ['money_id' => '', 'amount' => ''],
        ]])->assertRedirect(route('money.index'));

        $this->assertSame('-10100.00', \App\Services\Ledger::balance($customer->id));
        $this->assertSame('100.00', \App\Services\Ledger::balance($this->cash('USD')->id));
        $this->assertSame('3000.00', \App\Services\Ledger::balance($this->cash('AFN')->id));
        $entry = \App\Models\JournalEntry::latest('id')->firstOrFail();
        $this->assertSame(3, $entry->lines()->count());
        $this->assertStringContainsString('100.00 USD + 3,000.00 AFN', $entry->description);

        // money out in two currencies at the rate of the day, the amount in his currency typed by hand on one line
        $this->post(route('money.store', ['kind' => 'out']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [
            ['money_id' => $this->cash('USD')->id, 'amount' => '10'],
            ['money_id' => $this->cash('USD')->id, 'amount' => '5', 'other_amount' => '360'],
        ]])->assertRedirect();
        $this->assertSame('-9040.00', \App\Services\Ledger::balance($customer->id));   // 10 x 70 + 360 = 1,060 back to him

        // a line without an account, or nothing at all, is refused and nothing is saved
        $count = \App\Models\JournalEntry::count();
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => '', 'amount' => '50']]])->assertSessionHas('danger');
        $this->post(route('money.store', ['kind' => 'in']), ['date' => today_iso(), 'other_id' => $customer->id, 'lines' => [['money_id' => '', 'amount' => '']]])->assertSessionHas('danger');
        $this->assertSame($count, \App\Models\JournalEntry::count());
    }
}
