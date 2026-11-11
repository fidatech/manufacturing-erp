<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\Currency;
use App\Services\AssetService;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('assets.view');

        $writtenOff = AssetDepreciation::groupBy('asset_id')->selectRaw('asset_id, SUM(amount_base) as total')->toBase()->pluck('total', 'asset_id');
        $assets = Asset::with('currency')
            ->when($request->query('class'), fn ($q, $class) => $q->where('class', $class))
            ->when($request->query('kind'), fn ($q, $kind) => $q->where('kind', $kind))
            ->orderBy('status')->orderByDesc('purchase_date')->get();

        $rows = [];
        $totals = ['cost' => '0', 'off' => '0', 'value' => '0'];
        foreach ($assets as $asset) {
            $off = Num::money($writtenOff[$asset->id] ?? 0);
            $value = $asset->status === 'active' ? Num::money(Num::sub($asset->cost_base, $off)) : '0.00';
            $rows[] = ['asset' => $asset, 'off' => $off, 'value' => $value];
            if ($asset->status === 'active') {
                $totals['cost'] = Num::add($totals['cost'], $asset->cost_base);
                $totals['off'] = Num::add($totals['off'], $off);
                $totals['value'] = Num::add($totals['value'], $value);
            }
        }

        return view('assets.index', [
            'rows' => $rows,
            'totals' => $totals,
            'base' => Rates::base()->code,
            'periods' => AssetDepreciation::groupBy('period')->selectRaw('period, SUM(amount_base) as total, COUNT(*) as assets')->orderByDesc('period')->limit(12)->toBase()->get(),
            'moneyAccounts' => Account::money()->active()->with('currency')->orderBy('name')->get(),
        ]);
    }

    /** Depreciation (things you can touch) or amortization (licences, software): the monthly loss of value. */
    public function writeOff(Request $request): View
    {
        Gate::authorize('assets.view');

        $kind = $request->route('kind') === 'amortization' ? 'intangible' : 'tangible';
        $writtenOff = AssetDepreciation::groupBy('asset_id')->selectRaw('asset_id, SUM(amount_base) as total')->toBase()->pluck('total', 'asset_id');

        $rows = Asset::where('kind', $kind)->where('status', 'active')->where('life_months', '>', 0)->orderBy('name')->get()->map(function (Asset $asset) use ($writtenOff) {
            $off = Num::money($writtenOff[$asset->id] ?? 0);

            return ['asset' => $asset, 'off' => $off, 'monthly' => Num::money(Num::div(Num::sub($asset->cost_base, $asset->salvage_base), $asset->life_months)), 'value' => Num::money(Num::sub($asset->cost_base, $off))];
        });

        return view('assets.write-off', [
            'kind' => $kind,
            'title' => $kind === 'tangible' ? __('Depreciation') : __('Amortization'),
            'rows' => $rows,
            'base' => Rates::base()->code,
            'periods' => AssetDepreciation::whereHas('asset', fn ($q) => $q->where('kind', $kind))->groupBy('period')
                ->selectRaw('period, SUM(amount_base) as total, COUNT(*) as assets')->orderByDesc('period')->limit(12)->toBase()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('assets.manage');

        return view('assets.form', [
            'currencies' => Currency::where('is_active', true)->orderByDesc('is_base')->orderBy('code')->get(),
            'categories' => \App\Models\Lookup::options('asset_category'),
            'payers' => Account::active()->with(['currency', 'type'])->whereHas('type', fn ($q) => $q->where('is_money', true)->orWhere('key', 'supplier'))->orderBy('name')->get(),
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'class' => ['required', 'in:fixed,current'],
            'kind' => ['required', 'in:tangible,intangible'],
            'category' => ['nullable', 'string', 'max:150'],
            'purchase_date' => ['required', 'date'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'paid_from_id' => ['nullable', 'exists:accounts,id'],
            'life_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
            'salvage_base' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = AssetService::create($data);

        return redirect()->route('assets.index')->with('success', __(':name saved.', ['name' => $asset->name]));
    }

    /** Write off one month of value for every asset. */
    public function depreciate(Request $request): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'kind' => ['nullable', 'in:tangible,intangible']]);
        $result = AssetService::runMonth($data['period'], $data['kind'] ?? null);

        return back()->with($result['count'] ? 'success' : 'info', $result['count']
            ? __(':count assets written off for :period: :total.', ['count' => $result['count'], 'period' => $data['period'], 'total' => money($result['total'], Rates::base()->code)])
            : __('Nothing to write off for :period. It was already done, or no asset has a life in months.', ['period' => $data['period']]));
    }

    public function undo(Request $request): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $data = $request->validate(['period' => ['required', 'date_format:Y-m'], 'kind' => ['nullable', 'in:tangible,intangible']]);
        AssetService::undoMonth($data['period'], $data['kind'] ?? null);

        return back()->with('success', __('The write-off of :period was taken back.', ['period' => $data['period']]));
    }

    public function dispose(Request $request): RedirectResponse
    {
        Gate::authorize('assets.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'sold_for' => ['nullable', 'numeric', 'min:0'],
            'money_id' => ['nullable', 'exists:accounts,id'],
        ]);
        $asset = Asset::findOrFail($request->route('id'));
        AssetService::dispose($asset, $data['date'], $data['sold_for'] ?? 0, isset($data['money_id']) ? (int) $data['money_id'] : null);

        return back()->with('success', __(':name was removed.', ['name' => $asset->name]));
    }
}
