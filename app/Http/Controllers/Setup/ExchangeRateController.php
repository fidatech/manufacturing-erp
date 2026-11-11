<?php

namespace App\Http\Controllers\Setup;

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
        return ExchangeRate::query()->when($request->query('currency'), fn ($q, $id) => $q->where('currency_id', $id));
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

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['created_by'] = $record?->created_by ?? $request->user()->id;

        return $data;
    }
}
