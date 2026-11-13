<?php

namespace App\Http\Controllers\Setup;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use App\Exceptions\UserError;
use App\Http\Controllers\CrudController;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExchangeRateController extends CrudController
{
    protected string $model = ExchangeRate::class;

    protected string $route = 'rates';

    protected string $permission = 'setup';

    protected string $title = 'Exchange Rates';

    protected string $singular = 'Rate';

    protected string $icon = 'arrow-right-arrow-left';

    protected string $orderBy = 'date';

    protected string $orderDirection = 'desc';

    protected array $search = [];

    protected array $with = ['currency'];

    protected function query(Request $request): Builder
    {
        return ExchangeRate::query()
            ->when((int) $request->query('currency'), fn ($q, $id) => $q->where('currency_id', $id))
            ->when($request->query('year'), fn ($q, $year) => $q->whereYear('date', (int) $year));
    }

    protected function filters(Request $request): array
    {
        $years = ExchangeRate::orderByDesc('date')->pluck('date')->map(fn ($date) => substr((string) $date, 0, 4))->unique()->values();

        return [
            ['name' => 'currency', 'label' => __('All currencies'), 'value' => (string) $request->query('currency'),
                'options' => Currency::where('is_base', false)->orderBy('code')->pluck('code', 'id')->all()],
            ['name' => 'year', 'label' => __('All years'), 'value' => (string) $request->query('year'), 'options' => $years->combine($years)->all()],
        ];
    }

    protected function fields(?Model $record): array
    {
        $base = Rates::base()->code;

        return [
            ['name' => 'currency_id', 'label' => 'Currency', 'type' => 'select', 'default' => request('currency'),
                'options' => Currency::where('is_base', false)->where('is_active', true)->orderBy('code')->pluck('code', 'id')->all(),
                'rules' => ['required', 'exists:currencies,id']],
            ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => today_iso(),
                'rules' => ['required', 'date', Rule::unique('exchange_rates')->where('currency_id', request('currency_id'))->ignore($record?->id)]],
            ['name' => 'rate', 'label' => __('1 :base = how many?', ['base' => $base]), 'type' => 'decimal', 'rules' => ['required', 'numeric', 'gt:0'],
                'help' => __('Example: if 1 :base is 70.50 AFN, write 70.50', ['base' => $base])],
        ];
    }

    protected function columns(): array
    {
        $base = Rates::base()->code;

        return [
            'Date' => fn (ExchangeRate $r) => fmt_date($r->date),
            'Currency' => fn (ExchangeRate $r) => $r->currency->code,
            'Rate' => fn (ExchangeRate $r) => '1 '.$base.' = '.Num::format($r->rate, 6, true).' '.$r->currency->code,
        ];
    }

    /**
     * Today's rates in one go, from the pop-up in the top bar: a rate for every currency on one date.
     * A rate already saved for that day is replaced; a box left empty is not touched.
     */
    public function daily(Request $request): RedirectResponse
    {
        Gate::authorize('setup.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*' => ['nullable', 'numeric', 'gt:0'],
        ], [], ['rates.*' => __('exchange rate')]);

        $currencies = Currency::where('is_base', false)->where('is_active', true)->pluck('id')->all();
        $saved = 0;
        DB::transaction(function () use ($data, $currencies, $request, &$saved) {
            foreach ($data['rates'] as $currencyId => $rate) {
                if ($rate === null || $rate === '' || ! in_array((int) $currencyId, $currencies, true)) {
                    continue;
                }
                $row = ExchangeRate::whereDate('date', $data['date'])->firstOrNew(['currency_id' => (int) $currencyId]);
                $row->fill(['date' => $data['date'], 'rate' => Num::round($rate, 6), 'created_by' => $row->created_by ?? $request->user()->id])->save();
                $saved++;
            }
        });
        if ($saved === 0) {
            throw new UserError(__('Write at least one rate.'));
        }
        Rates::forget();

        return back()->with('success', __('Rates of :date saved.', ['date' => fmt_date($data['date'])]));
    }

    /** What the pop-up shows: every currency with its newest rate. */
    public static function latest(): array
    {
        $rows = [];
        foreach (Currency::where('is_base', false)->where('is_active', true)->orderBy('code')->get() as $currency) {
            $last = ExchangeRate::where('currency_id', $currency->id)->orderByDesc('date')->first();
            $day = $last ? substr((string) $last->getRawOriginal('date'), 0, 10) : null;
            // 'today' tells whether this currency already has its rate of the day
            $rows[] = ['currency' => $currency, 'rate' => $last?->rate, 'date' => $last?->date, 'today' => $day === today_iso()];
        }

        return $rows;
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['created_by'] = $record?->created_by ?? $request->user()->id;

        return $data;
    }
}
