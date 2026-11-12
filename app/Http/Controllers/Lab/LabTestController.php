<?php

namespace App\Http\Controllers\Lab;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\LabTest;
use App\Models\Lookup;
use App\Models\ProductionBatch;
use App\Services\Sequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Laboratory and quality control.
 * A test belongs to a batch we made, or to a raw material that arrived.
 * It is a list of things checked (Brix, pH, colour, taste ...) with what was found.
 * A test passes only when every line passes. The result is shown on the batch.
 */
class LabTestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('lab.view');

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'status' => ['nullable', 'in:passed,failed'], 'subject' => ['nullable', 'in:product,raw'], 'q' => ['nullable', 'string', 'max:100']]);

        $tests = LabTest::with(['item', 'batch'])
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['subject'] ?? null, fn ($q, $subject) => $q->where('subject', $subject))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('number', 'like', "%$term%")->orWhere('batch_no', 'like', "%$term%")->orWhereHas('item', fn ($p) => $p->where('name', 'like', "%$term%"))))
            ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();

        return view('lab.index', [
            'tests' => $tests,
            'filters' => $filters,
            'waiting' => ProductionBatch::with('item')->where('status', 'posted')->where('qc_status', 'pending')->orderBy('date')->limit(20)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('lab.manage');

        $batch = $request->query('batch') ? ProductionBatch::with('item')->where('status', 'posted')->find($request->query('batch')) : null;
        $subject = $batch ? 'product' : ($request->query('subject') === 'raw' ? 'raw' : 'product');

        return view('lab.form', [
            'batch' => $batch,
            'subject' => $subject,
            'items' => Item::where('type', $subject === 'raw' ? 'raw' : 'product')->where('is_active', true)->orderBy('name')->get(),
            'batches' => ProductionBatch::with('item')->where('status', 'posted')->orderByDesc('date')->limit(100)->get(),
            'parameters' => Lookup::options('qc_parameter'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('lab.manage');

        $data = $request->validate([
            'subject' => ['required', 'in:product,raw'],
            'date' => ['required', 'date'],
            'production_batch_id' => ['nullable', 'exists:production_batches,id'],
            'item_id' => ['nullable', 'exists:items,id'],
            'batch_no' => ['nullable', 'string', 'max:60'],
            'tested_by' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.parameter' => ['nullable', 'string', 'max:150'],
            'lines.*.standard' => ['nullable', 'string', 'max:150'],
            'lines.*.result' => ['nullable', 'string', 'max:150'],
            'lines.*.ok' => ['nullable'],
        ]);

        $batch = ! empty($data['production_batch_id']) ? ProductionBatch::findOrFail($data['production_batch_id']) : null;
        $itemId = $batch?->item_id ?? ($data['item_id'] ?? null);
        if (! $itemId) {
            throw new UserError(__('Choose the batch or the item that was tested.'));
        }

        $results = [];
        foreach ($data['lines'] as $line) {
            if (empty($line['parameter'])) {
                continue;
            }
            $results[] = ['parameter' => $line['parameter'], 'standard' => $line['standard'] ?? '', 'result' => $line['result'] ?? '', 'ok' => ! empty($line['ok'])];
        }
        if (! $results) {
            throw new UserError(__('Add at least one thing that was checked.'));
        }
        $passed = collect($results)->every(fn ($row) => $row['ok']);

        $test = DB::transaction(function () use ($data, $batch, $itemId, $results, $passed, $request) {
            $test = LabTest::create([
                'number' => Sequence::next('QC'),
                'date' => $data['date'],
                'subject' => $batch ? 'product' : $data['subject'],
                'item_id' => $itemId,
                'batch_no' => $batch?->batch_no ?? ($data['batch_no'] ?? null),
                'production_batch_id' => $batch?->id,
                'results' => $results,
                'status' => $passed ? 'passed' : 'failed',
                'tested_by' => ($data['tested_by'] ?? null) ?: $request->user()->name,
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            // the newest test decides what the batch shows
            $batch?->update(['qc_status' => $test->status]);

            return $test;
        });

        return redirect()->route('lab.show', ['id' => $test->id])->with($passed ? 'success' : 'warning', $passed ? __('Test :number saved: passed.', ['number' => $test->number]) : __('Test :number saved: FAILED.', ['number' => $test->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('lab.view');

        return view('lab.show', ['test' => LabTest::with(['item', 'batch'])->findOrFail($request->route('id'))]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('lab.manage');

        $test = LabTest::findOrFail($request->route('id'));
        DB::transaction(function () use ($test) {
            $batch = $test->batch;
            $test->delete();
            // the batch goes back to its previous test, or to "not tested"
            $batch?->update(['qc_status' => $batch->tests()->orderByDesc('id')->value('status') ?? 'pending']);
        });

        return redirect()->route('lab.index')->with('success', __('Test deleted.'));
    }
}
