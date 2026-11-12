<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use App\Support\Num;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Currency exchange, the way an exchange shop writes it:
 * sell an amount of one currency at a rate, multiply or divide, and get the amount bought.
 *
 * An "account holder" is one name with an account in several currencies: the cash boxes
 * (Cash USD, Cash AFN ...), a bank, a sarafi or a customer. The exchange moves the money
 * from the holder's account in the currency sold to his account in the currency bought.
 */
class ExchangeService
{
    /**
     * Holders that have accounts in two or more currencies.
     *
     * @return Collection<string, array{key: string, name: string, kind: string, accounts: array<int, Account>}>
     */
    public static function holders(): Collection
    {
        $codes = Currency::pluck('code')->map(fn ($code) => preg_quote($code, '/'))->implode('|');

        return Account::active()->with(['currency', 'type'])->whereNull('system_key')
            ->whereHas('type', fn ($q) => $q->where('is_money', true)->orWhere('is_party', true))
            ->orderBy('name')->get()
            ->groupBy(function (Account $account) use ($codes) {
                // "Cash USD" and "Cash AFN" are the same holder: the currency at the end of the name is dropped
                $name = $codes === '' ? $account->name : preg_replace('/[\s\-(]*\b('.$codes.')\)?\s*$/iu', '', $account->name);

                return $account->account_type_id.'|'.mb_strtolower(trim($name) !== '' ? trim($name) : $account->name);
            })
            ->map(function (Collection $accounts, string $key) use ($codes) {
                $first = $accounts->first();
                $name = $codes === '' ? $first->name : trim(preg_replace('/[\s\-(]*\b('.$codes.')\)?\s*$/iu', '', $first->name));

                return [
                    'key' => $key,
                    'name' => $name !== '' ? $name : $first->name,
                    'kind' => $first->type->name,
                    'accounts' => $accounts->keyBy('currency_id')->all(),
                ];
            })
            ->filter(fn (array $holder) => count($holder['accounts']) > 1);
    }

    /**
     * The rate of the rates table between two currencies on a date, written the way people say it:
     * always 1 or more, with "multiply" or "divide" telling which way it works.
     *
     * @return array{rate: string, operation: string}
     */
    public static function tableRate(int $sellCurrencyId, int $buyCurrencyId, string $date): array
    {
        $sell = Rates::rate($sellCurrencyId, $date);
        $buy = Rates::rate($buyCurrencyId, $date);

        return Num::cmp($buy, $sell) >= 0
            ? ['rate' => Num::round(Num::div($buy, $sell), 6), 'operation' => 'multiply']
            : ['rate' => Num::round(Num::div($sell, $buy), 6), 'operation' => 'divide'];
    }

    /** The amount bought for an amount sold. */
    public static function convert(mixed $amount, mixed $rate, string $operation): string
    {
        return Num::money($operation === 'divide' ? Num::div($amount, $rate) : Num::mul($amount, $rate));
    }

    /**
     * Save an exchange and move the money, all or nothing.
     *
     * @param  array{date: string, sell_amount: mixed, rate?: mixed, operation?: ?string, buy_amount?: mixed, description?: ?string}  $data
     */
    public static function create(Account $sell, Account $buy, array $data): CurrencyExchange
    {
        if ($sell->id === $buy->id || $sell->currency_id === $buy->currency_id) {
            throw new UserError(__('Choose two different currencies.'));
        }

        $date = $data['date'];
        $sold = Num::money($data['sell_amount']);
        if (! Num::isPositive($sold)) {
            throw new UserError(__('The selling amount must be more than zero.'));
        }

        // no rate typed: the rate of the rates table for that day
        if (Num::isPositive($data['rate'] ?? 0) && in_array($data['operation'] ?? null, CurrencyExchange::OPERATIONS, true)) {
            $rate = Num::round($data['rate'], 6);
            $operation = $data['operation'];
        } else {
            ['rate' => $rate, 'operation' => $operation] = self::tableRate($sell->currency_id, $buy->currency_id, $date);
        }
        if (! Num::isPositive($rate)) {
            throw new UserError(__('The exchange rate must be more than zero.'));
        }

        $bought = self::convert($sold, $rate, $operation);
        // a buying amount typed by hand is kept (a shop rounds it), as long as it agrees with the rate
        if (Num::isPositive($data['buy_amount'] ?? 0)) {
            $given = Num::money($data['buy_amount']);
            $gap = Num::abs(Num::sub($given, $bought));
            if (Num::cmp($gap, Num::div($bought, '100')) > 0 && Num::cmp($gap, '0.01') > 0) {
                throw new UserError(__('The buying amount does not agree with the selling amount and the rate.'));
            }
            $bought = $given;
        }
        if (! Num::isPositive($bought)) {
            throw new UserError(__('The buying amount must be more than zero.'));
        }

        // both sides carry the same value in the main currency, so an exchange alone makes no profit or loss
        $baseId = Rates::base()->id;
        $base = match (true) {
            $sell->currency_id === $baseId => $sold,
            $buy->currency_id === $baseId => $bought,
            default => Rates::toBase($sold, $sell->currency_id, $date),
        };

        return DB::transaction(function () use ($sell, $buy, $data, $date, $sold, $bought, $rate, $operation, $base) {
            $exchange = CurrencyExchange::create([
                'number' => Sequence::next('EX'),
                'date' => $date,
                'sell_account_id' => $sell->id,
                'buy_account_id' => $buy->id,
                'sell_amount' => $sold,
                'rate' => $rate,
                'operation' => $operation,
                'buy_amount' => $bought,
                'description' => ($data['description'] ?? null) ?: null,
                'created_by' => auth()->id(),
            ]);

            $text = $exchange->description ?: __('Currency exchange').': '.money($sold, $sell->currency->code).' → '.money($bought, $buy->currency->code);
            Ledger::post('exchange', $date, $exchange->number.' '.$text, [
                ['account' => $buy, 'debit' => $bought, 'base' => $base],
                ['account' => $sell, 'credit' => $sold, 'base' => $base],
            ], $exchange);

            return $exchange;
        });
    }

    public static function cancel(CurrencyExchange $exchange): void
    {
        if ($exchange->isCancelled()) {
            return;
        }
        DB::transaction(function () use ($exchange) {
            Ledger::voidSource($exchange);
            $exchange->update(['cancelled_at' => now()]);
        });
    }
}
