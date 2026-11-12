<?php

namespace App\Support;

/** The plain name and the colour of each kind of money movement, for statements and lists. */
class EntryType
{
    /** @return array{label: string, color: string} */
    public static function meta(?string $type): array
    {
        $all = [
            'receipt' => [__('Money in'), 'success'],
            'payment' => [__('Money out'), 'danger'],
            'expense' => [__('Expense'), 'warning'],
            'transfer' => [__('Transfer'), 'info'],
            'exchange' => [__('Currency exchange'), 'info'],
            'profit' => [__('Profit paid'), 'warning'],
            'sale' => [__('Sale'), 'primary'],
            'sale_return' => [__('Sale return'), 'danger'],
            'purchase' => [__('Purchase'), 'info'],
            'purchase_return' => [__('Purchase return'), 'danger'],
            'opening' => [__('Opening balance'), 'secondary'],
            'journal' => [__('Day book'), 'secondary'],
            'payroll' => [__('Salary'), 'info'],
            'production' => [__('Production'), 'primary'],
            'asset' => [__('Asset'), 'secondary'],
            'depreciation' => [__('Depreciation'), 'secondary'],
            'stock_count' => [__('Stock count'), 'warning'],
        ];
        [$label, $color] = $all[$type] ?? [ucfirst(str_replace('_', ' ', (string) $type)), 'secondary'];

        return ['label' => $label, 'color' => $color];
    }

    /** Colour of a stock movement. */
    public static function stockColor(?string $type): string
    {
        return [
            'purchase' => 'info', 'purchase_return' => 'danger', 'sale' => 'primary', 'sale_return' => 'danger',
            'opening' => 'secondary', 'transfer_in' => 'info', 'transfer_out' => 'info', 'count' => 'warning',
            'production_in' => 'success', 'production_out' => 'warning',
        ][$type] ?? 'secondary';
    }
}
