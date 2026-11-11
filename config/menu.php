<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
|
|   ['heading' => 'Text']
|   ['label' => 'Text', 'icon' => 'gauge-high', 'route' => 'dashboard']
|   ['label' => 'Text', 'icon' => 'box', 'children' => [
|       ['label' => 'Text', 'route' => 'items.index', 'params' => ['type' => 'product'], 'active' => 'items.*', 'can' => 'items.view'],
|   ]]
|
| icon    a Font Awesome solid icon name; after adding one run: php tools/build-assets.php
| params  route parameters (and query values) of the link
| active  route-name pattern that highlights the entry (defaults to the route)
| can     permission needed to see the entry
|
*/

return [
    ['label' => 'Dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],

    ['heading' => 'Daily work'],
    ['label' => 'Sales', 'icon' => 'file-invoice-dollar', 'children' => [
        ['label' => 'New sale', 'route' => 'sales.create', 'params' => ['type' => 'invoice'], 'can' => 'sales.manage'],
        ['label' => 'Sales', 'route' => 'sales.index', 'params' => ['type' => 'invoice'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Quotations', 'route' => 'sales.index', 'params' => ['type' => 'quotation'], 'can' => 'sales.view'],
        ['label' => 'Deliveries to send', 'route' => 'sales.index', 'params' => ['type' => 'invoice', 'delivery' => 'pending'], 'can' => 'sales.view'],
        ['label' => 'Sale returns', 'route' => 'sales.index', 'params' => ['type' => 'return'], 'can' => 'sales.view'],
    ]],
    ['label' => 'Purchases', 'icon' => 'cart-flatbed', 'children' => [
        ['label' => 'New purchase', 'route' => 'purchases.create', 'params' => ['type' => 'invoice'], 'can' => 'purchases.manage'],
        ['label' => 'Purchases', 'route' => 'purchases.index', 'params' => ['type' => 'invoice'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Purchase orders', 'route' => 'purchases.index', 'params' => ['type' => 'order'], 'can' => 'purchases.view'],
        ['label' => 'Goods on the way', 'route' => 'purchases.index', 'params' => ['type' => 'invoice', 'waiting' => 1], 'can' => 'purchases.view'],
        ['label' => 'Purchase returns', 'route' => 'purchases.index', 'params' => ['type' => 'return'], 'can' => 'purchases.view'],
    ]],
    ['label' => 'Money', 'icon' => 'money-bill-wave', 'children' => [
        ['label' => 'All money entries', 'route' => 'money.index', 'can' => 'money.view'],
        ['label' => 'Money in', 'route' => 'money.create', 'params' => ['kind' => 'in'], 'can' => 'money.manage'],
        ['label' => 'Money out', 'route' => 'money.create', 'params' => ['kind' => 'out'], 'can' => 'money.manage'],
        ['label' => 'Expense', 'route' => 'money.create', 'params' => ['kind' => 'expense'], 'can' => 'money.manage'],
        ['label' => 'Transfer / exchange', 'route' => 'money.create', 'params' => ['kind' => 'transfer'], 'can' => 'money.manage'],
        ['label' => 'Day book (roznamcha)', 'route' => 'journal.index', 'active' => 'journal.*', 'can' => 'journal.view'],
    ]],
    ['label' => 'Stock', 'icon' => 'boxes-stacked', 'children' => [
        ['label' => 'Stock on hand', 'route' => 'stock.index', 'can' => 'inventory.view'],
        ['label' => 'Batches and expiry', 'route' => 'stock.batches', 'can' => 'inventory.view'],
        ['label' => 'Move between warehouses', 'route' => 'stock-docs.index', 'params' => ['type' => 'transfer'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
        ['label' => 'Physical count', 'route' => 'stock-docs.index', 'params' => ['type' => 'count'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
        ['label' => 'Opening stock', 'route' => 'stock-docs.index', 'params' => ['type' => 'opening'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
        ['label' => 'Products', 'route' => 'items.index', 'params' => ['type' => 'product'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Raw materials', 'route' => 'items.index', 'params' => ['type' => 'raw'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Item groups', 'route' => 'item-categories.index', 'active' => 'item-categories.*', 'can' => 'items.view'],
    ]],

    ['heading' => 'People and accounts'],
    ['label' => 'Customers', 'icon' => 'user-group', 'route' => 'accounts.index', 'params' => ['type' => 'customer'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
    ['label' => 'Suppliers', 'icon' => 'truck-field', 'route' => 'accounts.index', 'params' => ['type' => 'supplier'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
    ['label' => 'Banks, sarafi, cash', 'icon' => 'building-columns', 'children' => [
        ['label' => 'Cash boxes', 'route' => 'accounts.index', 'params' => ['type' => 'cash'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Banks', 'route' => 'accounts.index', 'params' => ['type' => 'bank'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Sarafi', 'route' => 'accounts.index', 'params' => ['type' => 'sarafi'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Expense categories', 'route' => 'accounts.index', 'params' => ['type' => 'expense'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Income categories', 'route' => 'accounts.index', 'params' => ['type' => 'income'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
    ]],
    ['label' => 'Owners and loans', 'icon' => 'handshake', 'children' => [
        ['label' => 'Shareholders', 'route' => 'accounts.index', 'params' => ['type' => 'shareholder'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Capital put in', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'shareholder'], 'can' => 'money.manage'],
        ['label' => 'Money taken out', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'shareholder'], 'can' => 'money.manage'],
        ['label' => 'Share profit', 'route' => 'money.profit', 'can' => 'money.manage'],
        ['label' => 'Loans and liabilities', 'route' => 'accounts.index', 'params' => ['type' => 'liability'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Loan received', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'liability'], 'can' => 'money.manage'],
        ['label' => 'Loan paid back', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'liability'], 'can' => 'money.manage'],
    ]],
    ['label' => 'Employees', 'icon' => 'id-badge', 'children' => [
        ['label' => 'Employees', 'route' => 'accounts.index', 'params' => ['type' => 'employee'], 'active' => 'accounts.*', 'can' => 'hr.view'],
        ['label' => 'Pay salary or advance', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'employee'], 'can' => 'money.manage'],
    ]],

    ['heading' => 'Office'],
    ['label' => 'Chart of accounts', 'icon' => 'sitemap', 'route' => 'chart', 'can' => 'accounts.view'],
    ['label' => 'Documents', 'icon' => 'folder-open', 'route' => 'documents.index', 'can' => 'documents.view'],
    ['label' => 'Setup', 'icon' => 'gear', 'children' => [
        ['label' => 'Company', 'route' => 'company.edit', 'can' => 'setup.view'],
        ['label' => 'Currencies', 'route' => 'currencies.index', 'active' => 'currencies.*', 'can' => 'setup.view'],
        ['label' => 'Exchange rates', 'route' => 'rates.index', 'active' => 'rates.*', 'can' => 'setup.view'],
        ['label' => 'Warehouses', 'route' => 'warehouses.index', 'active' => 'warehouses.*', 'can' => 'setup.view'],
        ['label' => 'Warehouse managers', 'route' => 'accounts.index', 'params' => ['type' => 'warehouse_manager'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Units', 'route' => 'units.index', 'active' => 'units.*', 'can' => 'setup.view'],
        ['label' => 'Account types', 'route' => 'account-types.index', 'active' => 'account-types.*', 'can' => 'setup.view'],
    ]],
    ['label' => 'Users', 'icon' => 'users-gear', 'children' => [
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
        ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'users.view'],
        ['label' => 'Audit log', 'route' => 'audit.index', 'can' => 'audit.view'],
    ]],
];
