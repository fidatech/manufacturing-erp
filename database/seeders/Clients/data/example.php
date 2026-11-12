<?php

// The shape of a client data file. Copy it to {client}.php in this folder and fill it in.
// Real client files are NOT kept in the repository (see .gitignore): they hold the client's own figures.
// The figures below are made up and only show the format; the tests load this file.

return [
    'opening_date' => '2026-01-01',
    'settings' => ['company_name' => 'Example Factory', 'company_phone' => '0700000000', 'company_address' => 'Kabul'],
    'logo' => null,
    // 1 main currency = rate units
    'rates' => [['currency' => 'AFN', 'date' => '2026-01-01', 'rate' => '70']],
    'warehouses' => [['name' => 'Example Store', 'location' => null]],
    'items' => [
        ['key' => 'raw|Sugar', 'type' => 'raw', 'name' => 'Sugar', 'unit' => 'Kilogram', 'packing' => 'Bag 50 kg'],
        ['key' => 'product|Example Syrup', 'type' => 'product', 'name' => 'Example Syrup', 'unit' => 'Can'],
    ],
    'stock' => [
        ['warehouse' => 'Example Store', 'item' => 'raw|Sugar', 'qty' => '500', 'unit_cost' => '0.8', 'batch_no' => 'B1', 'expiry_date' => '2028-01-01'],
        ['warehouse' => 'Example Store', 'item' => 'product|Example Syrup', 'qty' => '10'],
    ],
    // side: debit = they owe us / we have it, credit = we owe them / capital
    'accounts' => [
        ['type' => 'customer', 'name' => 'Example Customer', 'currency' => 'AFN', 'phone' => '0700000001', 'address' => 'Kabul', 'opening' => '7000', 'side' => 'debit'],
        ['type' => 'supplier', 'name' => 'Example Supplier', 'currency' => 'USD'],
        ['type' => 'bank', 'name' => 'Example Bank', 'currency' => 'USD', 'meta' => ['bank_name' => 'Example'], 'opening' => '250', 'side' => 'debit'],
        ['type' => 'shareholder', 'name' => 'Example Owner', 'currency' => 'USD', 'opening' => '1000', 'side' => 'credit'],
    ],
    'assets' => [['name' => 'Example Mixer', 'cost' => '900', 'currency' => 'USD', 'kind' => 'tangible']],
    'recipes' => [
        ['product' => 'Example Syrup', 'name' => 'Example Syrup 20 kg', 'method' => 'liquid', 'pack_weight' => '20', 'extra_cost' => '0',
            'lines' => [['item' => 'Sugar', 'qty' => '5', 'cost' => '0.8'], ['item' => 'Flavour', 'qty' => '0.1', 'cost' => '12']]],
    ],
    'employees' => ['currency' => 'AFN', 'list' => [['name' => 'Example Worker', 'father_name' => 'Example', 'job_title' => 'Worker', 'phone' => '0700000002', 'salary' => '8000', 'salary_tax' => '0', 'gender' => 'male']]],
];
