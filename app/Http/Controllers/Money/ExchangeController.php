<?php

namespace App\Http\Controllers\Money;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\CompanyController;
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
            ->whereNull('cancelled_at')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('money.exchange', ['exchanges' => $exchanges, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('money.manage');

        return $this->form(null);
    }

    public function edit(Request $request): View
    {
        Gate::authorize('money.manage');

        return $this->form(CurrencyExchange::with(['sellAccount', 'buyAccount'])->whereNull('cancelled_at')->findOrFail($request->route('id')));
    }

    private function form(?CurrencyExchange $exchange): View
    {
        return view('money.exchange-form', [
            'exchange' => $exchange,
            'holderKey' => $exchange ? ExchangeService::holderOf($exchange->sell_account_id) : null,
            'holders' => ExchangeService::holders(),
            'currencies' => Currency::orderBy('code')->get(),
            'rates' => $this->tableRates(old('date') ?: ($exchange?->date?->toDateString() ?? today_iso())),
        ]);
    }

    /** The receipt of one exchange, ready for the printer. */
    public function print(Request $request): View
    {
        Gate::authorize('money.view');

        return view('money.exchange-print', [
            'exchange' => CurrencyExchange::with(['sellAccount.currency', 'buyAccount.currency', 'creator'])->findOrFail($request->route('id')),
            'logo' => CompanyController::logoDataUri(),
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

        [$sell, $buy, $data] = $this->input($request);
        $exchange = ExchangeService::create($sell, $buy, $data);
        audit('created', $exchange, $this->words($exchange));

        return redirect()->route('exchange.index')->with('success', __('Saved as :number.', ['number' => $exchange->number]));
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $exchange = CurrencyExchange::whereNull('cancelled_at')->findOrFail($request->route('id'));
        [$sell, $buy, $data] = $this->input($request);
        ExchangeService::update($exchange, $sell, $buy, $data);
        audit('updated', $exchange, $this->words($exchange));

        return redirect()->route('exchange.index')->with('success', __(':number was changed.', ['number' => $exchange->number]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $exchange = CurrencyExchange::findOrFail($request->route('id'));
        ExchangeService::cancel($exchange);
        audit('deleted', $exchange, $this->words($exchange));

        return redirect()->route('exchange.index')->with('success', __(':number was deleted.', ['number' => $exchange->number]));
    }

    /** @return array{0: \App\Models\Account, 1: \App\Models\Account, 2: array<string, mixed>} */
    private function input(Request $request): array
    {
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

        return [$sell, $buy, $data];
    }

    private function words(CurrencyExchange $exchange): string
    {
        $exchange->loadMissing(['sellAccount.currency', 'buyAccount.currency']);

        return $exchange->number.' '.money($exchange->sell_amount, $exchange->sellAccount->currency->code).' → '.money($exchange->buy_amount, $exchange->buyAccount->currency->code);
    }
}
