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
}
