<?php

namespace App\Http\Controllers\Money;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use App\Services\ExchangeService;
use App\Services\Rates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Currency exchange: one screen with the form on top and the exchanges made below. */
class ExchangeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('money.view');

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $exchanges = CurrencyExchange::with(['sellAccount.currency', 'buyAccount.currency', 'creator'])
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('money.exchange', [
            'exchanges' => $exchanges,
            'filters' => $filters,
            'holders' => ExchangeService::holders(),
            'currencies' => Currency::orderBy('code')->get(),
            'rates' => $this->tableRates(old('date') ?: today_iso()),
            'base' => Rates::base()->code,
        ]);
    }

    /** The rates of the rates table on a date, for the form: {currency id: rate}. */
    public function rates(Request $request): JsonResponse
    {
        Gate::authorize('money.view');

        return response()->json($this->tableRates($request->validate(['date' => ['required', 'date']])['date']));
    }

    /** @return array<int, ?string> */
    private function tableRates(string $date): array
    {
        $rates = [];
        foreach (Currency::all() as $currency) {
            try {
                $rates[$currency->id] = Rates::rate($currency->id, $date);
            } catch (UserError) {
                // no rate saved for this currency yet: the user types one by hand
                $rates[$currency->id] = null;
            }
        }

        return $rates;
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'holder' => ['required', 'string', 'max:300'],
            'sell_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'buy_currency_id' => ['required', 'integer', 'exists:currencies,id', 'different:sell_currency_id'],
            'sell_amount' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'operation' => ['nullable', 'in:'.implode(',', CurrencyExchange::OPERATIONS)],
            'buy_amount' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], [
            'holder' => __('account'), 'sell_currency_id' => __('seller currency'), 'buy_currency_id' => __('buyer currency'),
            'sell_amount' => __('selling amount'), 'buy_amount' => __('buying amount'), 'rate' => __('exchange rate'),
        ]);

        $holder = ExchangeService::holders()->get($data['holder']);
        if (! $holder) {
            throw new UserError(__('Choose an account.'));
        }
        $sell = $holder['accounts'][(int) $data['sell_currency_id']] ?? null;
        $buy = $holder['accounts'][(int) $data['buy_currency_id']] ?? null;
        if (! $sell || ! $buy) {
            throw new UserError(__(':name has no account in that currency. Add it first on the account screen.', ['name' => $holder['name']]));
        }

        $exchange = ExchangeService::create($sell, $buy, $data);
        audit('created', $exchange, $exchange->number.' '.money($exchange->sell_amount, $sell->currency->code).' → '.money($exchange->buy_amount, $buy->currency->code));

        return redirect()->route('exchange.index')->with('success', __('Saved as :number.', ['number' => $exchange->number]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $exchange = CurrencyExchange::findOrFail($request->route('id'));
        ExchangeService::cancel($exchange);
        audit('cancelled', $exchange, $exchange->number);

        return back()->with('success', __(':number was cancelled.', ['number' => $exchange->number]));
    }
}
