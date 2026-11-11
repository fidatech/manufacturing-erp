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

    ['label' => 'Manufacturing', 'icon' => 'industry', 'children' => [
        ['label' => 'Final Products', 'route' => 'items.index', 'params' => ['type' => 'product'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Raw Materials', 'route' => 'items.index', 'params' => ['type' => 'raw'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Recipes & Formulas', 'route' => 'recipes.index', 'active' => 'recipes.*', 'can' => 'production.view'],
        ['label' => 'Record Production', 'route' => 'production.create', 'can' => 'production.manage'],
        ['label' => 'Production Batches', 'route' => 'production.index', 'active' => 'production.*', 'can' => 'production.view'],
        ['label' => 'Production Report', 'route' => 'production.report', 'can' => 'production.view'],
    ]],

    ['label' => 'Laboratory & QC', 'icon' => 'vial', 'children' => [
        ['label' => 'Lab Tests', 'route' => 'lab.index', 'active' => 'lab.*', 'can' => 'lab.view'],
        ['label' => 'Test a Batch', 'route' => 'lab.create', 'params' => ['subject' => 'product'], 'can' => 'lab.manage'],
        ['label' => 'Test a Raw Material', 'route' => 'lab.create', 'params' => ['subject' => 'raw'], 'can' => 'lab.manage'],
        ['label' => 'Batches Not Tested', 'route' => 'production.index', 'params' => ['qc' => 'pending'], 'can' => 'production.view'],
        ['label' => 'Failed Batches', 'route' => 'production.index', 'params' => ['qc' => 'failed'], 'can' => 'production.view'],
        ['label' => 'Test Parameters', 'route' => 'lookups.index', 'params' => ['type' => 'qc_parameter'], 'active' => 'lookups.*', 'can' => 'lab.view'],
    ]],

    ['label' => 'Accounts', 'icon' => 'address-book', 'children' => [
        ['label' => 'Cash', 'route' => 'accounts.index', 'params' => ['type' => 'cash'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Customers', 'route' => 'accounts.index', 'params' => ['type' => 'customer'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Suppliers', 'route' => 'accounts.index', 'params' => ['type' => 'supplier'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Banks', 'route' => 'accounts.index', 'params' => ['type' => 'bank'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Sarafi', 'route' => 'accounts.index', 'params' => ['type' => 'sarafi'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Employees', 'route' => 'accounts.index', 'params' => ['type' => 'employee'], 'active' => 'accounts.*', 'can' => 'hr.view'],
        ['label' => 'Warehouse Managers', 'route' => 'accounts.index', 'params' => ['type' => 'warehouse_manager'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
    ]],

    ['label' => 'Human Resource', 'icon' => 'id-badge', 'children' => [
        ['label' => 'Departments', 'route' => 'lookups.index', 'params' => ['type' => 'department'], 'active' => 'lookups.*', 'can' => 'hr.view'],
        ['label' => 'Designations', 'route' => 'lookups.index', 'params' => ['type' => 'designation'], 'active' => 'lookups.*', 'can' => 'hr.view'],
        ['label' => 'Employees', 'route' => 'accounts.index', 'params' => ['type' => 'employee'], 'active' => 'accounts.*', 'can' => 'hr.view'],
        ['label' => 'Attendance', 'route' => 'attendance.index', 'active' => 'attendance.*', 'can' => 'hr.view'],
        ['label' => 'Payroll', 'route' => 'payroll.index', 'active' => 'payroll.*', 'can' => 'hr.view'],
        ['label' => 'Leave Management', 'route' => 'hr.index', 'params' => ['screen' => 'leave'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Performance Appraisal', 'route' => 'hr.index', 'params' => ['screen' => 'appraisals'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Employee Contracts', 'route' => 'hr.index', 'params' => ['screen' => 'contracts'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Shift Scheduling', 'route' => 'hr.index', 'params' => ['screen' => 'shifts'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Overtime Requests', 'route' => 'hr.index', 'params' => ['screen' => 'overtime'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Allowances & Deductions', 'route' => 'hr.index', 'params' => ['screen' => 'adjustments'], 'active' => 'hr.*', 'can' => 'hr.view'],
        ['label' => 'Employee Advances', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'employee'], 'can' => 'money.manage'],
    ]],

    ['label' => 'Accounting', 'icon' => 'calculator', 'children' => [
        ['label' => 'Capital', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'shareholder'], 'can' => 'money.manage'],
        ['label' => 'Assets', 'route' => 'assets.index', 'can' => 'assets.view'],
        ['label' => 'Liabilities', 'route' => 'accounts.index', 'params' => ['type' => 'liability'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Loan Received', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'liability'], 'can' => 'money.manage'],
        ['label' => 'Liability Payments', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'liability'], 'can' => 'money.manage'],
        ['label' => 'Depreciation', 'route' => 'assets.write-off', 'params' => ['kind' => 'depreciation'], 'can' => 'assets.view'],
        ['label' => 'Amortization', 'route' => 'assets.write-off', 'params' => ['kind' => 'amortization'], 'can' => 'assets.view'],
        ['label' => 'Expenses', 'route' => 'money.index', 'params' => ['type' => 'expense'], 'can' => 'money.view'],
        ['label' => 'Day Book (Roznamcha)', 'route' => 'journal.index', 'active' => 'journal.*', 'can' => 'journal.view'],
    ]],

    ['label' => 'Shareholders', 'icon' => 'handshake', 'children' => [
        ['label' => 'Shareholder Management', 'route' => 'accounts.index', 'params' => ['type' => 'shareholder'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Capital Contribution', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'shareholder'], 'can' => 'money.manage'],
        ['label' => 'Withdrawals', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'shareholder'], 'can' => 'money.manage'],
        ['label' => 'Profit Distribution', 'route' => 'money.profit', 'can' => 'money.manage'],
        ['label' => 'Shareholder Statements', 'route' => 'reports.parties', 'params' => ['group' => 'shareholders'], 'can' => 'reports.view'],
    ]],

    ['label' => 'Purchases', 'icon' => 'cart-flatbed', 'children' => [
        ['label' => 'Purchase Orders', 'route' => 'purchases.index', 'params' => ['type' => 'order'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Supplier Quotations', 'route' => 'purchases.index', 'params' => ['type' => 'quotation'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Purchase Invoices', 'route' => 'purchases.index', 'params' => ['type' => 'invoice'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Goods Received Notes', 'route' => 'purchases.index', 'params' => ['type' => 'invoice', 'view' => 'grn'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Purchase Returns', 'route' => 'purchases.index', 'params' => ['type' => 'return'], 'active' => 'purchases.*', 'can' => 'purchases.view'],
        ['label' => 'Supplier Advances', 'route' => 'money.create', 'params' => ['kind' => 'out', 'group' => 'supplier'], 'can' => 'money.manage'],
    ]],

    ['label' => 'Sales', 'icon' => 'file-invoice-dollar', 'children' => [
        ['label' => 'Sale Orders', 'route' => 'sales.index', 'params' => ['type' => 'order'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Sale Quotations', 'route' => 'sales.index', 'params' => ['type' => 'quotation'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Sale Invoices', 'route' => 'sales.index', 'params' => ['type' => 'invoice'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Delivery Notes', 'route' => 'sales.index', 'params' => ['type' => 'invoice', 'view' => 'delivery'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Customers Price Lists', 'route' => 'price-lists.index', 'active' => 'price-lists.*', 'can' => 'sales.view'],
        ['label' => 'Dispatch Planning', 'route' => 'sales.index', 'params' => ['type' => 'invoice', 'view' => 'dispatch'], 'active' => 'sales.*', 'can' => 'sales.view'],
        ['label' => 'Sales Collections', 'route' => 'money.create', 'params' => ['kind' => 'in', 'group' => 'customer'], 'can' => 'money.manage'],
        ['label' => 'Sale Returns', 'route' => 'sales.index', 'params' => ['type' => 'return'], 'active' => 'sales.*', 'can' => 'sales.view'],
    ]],

    ['label' => 'Inventory', 'icon' => 'boxes-stacked', 'children' => [
        ['label' => 'Products', 'route' => 'items.index', 'params' => ['type' => 'product'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Raw Materials', 'route' => 'items.index', 'params' => ['type' => 'raw'], 'active' => 'items.*', 'can' => 'items.view'],
        ['label' => 'Stock in Hand', 'route' => 'stock.index', 'can' => 'inventory.view'],
        ['label' => 'Batches & Expiry', 'route' => 'stock.batches', 'can' => 'inventory.view'],
        ['label' => 'Stock Transfers', 'route' => 'stock-docs.index', 'params' => ['type' => 'transfer'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
        ['label' => 'Physical Count', 'route' => 'stock-docs.index', 'params' => ['type' => 'count'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
        ['label' => 'Opening Stock', 'route' => 'stock-docs.index', 'params' => ['type' => 'opening'], 'active' => 'stock-docs.*', 'can' => 'inventory.view'],
    ]],

    ['label' => 'Finance', 'icon' => 'money-bill-wave', 'children' => [
        ['label' => 'Transactions', 'route' => 'money.index', 'can' => 'money.view'],
        ['label' => 'Money In', 'route' => 'money.create', 'params' => ['kind' => 'in'], 'can' => 'money.manage'],
        ['label' => 'Money Out', 'route' => 'money.create', 'params' => ['kind' => 'out'], 'can' => 'money.manage'],
        ['label' => 'Currency Exchange', 'route' => 'money.create', 'params' => ['kind' => 'transfer', 'group' => 'exchange'], 'can' => 'money.manage'],
        ['label' => 'Transfers', 'route' => 'money.create', 'params' => ['kind' => 'transfer', 'group' => 'transfer'], 'can' => 'money.manage'],
        ['label' => 'Petty Cash', 'route' => 'petty-cash', 'can' => 'money.view'],
    ]],

    ['label' => 'Reports', 'icon' => 'chart-pie', 'children' => [
        ['label' => 'Cash Flow', 'route' => 'reports.cash', 'can' => 'reports.view'],
        ['label' => 'Balance Sheet', 'route' => 'reports.balance', 'can' => 'reports.view'],
        ['label' => 'Profit and Loss', 'route' => 'reports.profit', 'can' => 'reports.view'],
        ['label' => 'Trial Balance', 'route' => 'reports.trial', 'can' => 'reports.view'],
        ['label' => 'Accounts Statements', 'route' => 'chart', 'can' => 'accounts.view'],
        ['label' => 'Customers Report', 'route' => 'reports.parties', 'params' => ['group' => 'customers'], 'can' => 'reports.view'],
        ['label' => 'Suppliers Report', 'route' => 'reports.parties', 'params' => ['group' => 'suppliers'], 'can' => 'reports.view'],
        ['label' => 'Purchases Report', 'route' => 'reports.purchases', 'can' => 'reports.view'],
        ['label' => 'Sales Report', 'route' => 'reports.sales', 'can' => 'reports.view'],
        ['label' => 'Stock in Hands', 'route' => 'stock.index', 'can' => 'inventory.view'],
        ['label' => 'Creditors', 'route' => 'reports.parties', 'params' => ['group' => 'creditors'], 'can' => 'reports.view'],
        ['label' => 'Debtors', 'route' => 'reports.parties', 'params' => ['group' => 'debtors'], 'can' => 'reports.view'],
    ]],

    ['label' => 'File Manager', 'icon' => 'folder-open', 'children' => [
        ['label' => 'Documents', 'route' => 'documents.index', 'can' => 'documents.view'],
        ['label' => 'Document Types', 'route' => 'lookups.index', 'params' => ['type' => 'document_type'], 'active' => 'lookups.*', 'can' => 'documents.view'],
    ]],

    ['label' => 'Administration', 'icon' => 'users-gear', 'children' => [
        ['label' => 'Users', 'route' => 'users.index', 'active' => 'users.*', 'can' => 'users.view'],
        ['label' => 'Roles', 'route' => 'roles.index', 'active' => 'roles.*', 'can' => 'users.view'],
        ['label' => 'Permissions', 'route' => 'permissions', 'can' => 'users.view'],
        ['label' => 'Audit Activities', 'route' => 'audit.index', 'can' => 'audit.view'],
    ]],

    ['label' => 'Configurations', 'icon' => 'gear', 'children' => [
        ['label' => 'Company Settings', 'route' => 'company.edit', 'can' => 'setup.view'],
        ['label' => 'Currencies', 'route' => 'currencies.index', 'active' => 'currencies.*', 'can' => 'setup.view'],
        ['label' => 'Exchange Rates', 'route' => 'rates.index', 'active' => 'rates.*', 'can' => 'setup.view'],
        ['label' => 'Warehouses', 'route' => 'warehouses.index', 'active' => 'warehouses.*', 'can' => 'setup.view'],
        ['label' => 'Product Categories', 'route' => 'item-categories.index', 'active' => 'item-categories.*', 'can' => 'items.view'],
        ['label' => 'Expense Categories', 'route' => 'accounts.index', 'params' => ['type' => 'expense'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Income Categories', 'route' => 'accounts.index', 'params' => ['type' => 'income'], 'active' => 'accounts.*', 'can' => 'accounts.view'],
        ['label' => 'Asset Categories', 'route' => 'lookups.index', 'params' => ['type' => 'asset_category'], 'active' => 'lookups.*', 'can' => 'setup.view'],
        ['label' => 'Units', 'route' => 'units.index', 'active' => 'units.*', 'can' => 'setup.view'],
        ['label' => 'Chart of Accounts', 'route' => 'chart', 'can' => 'accounts.view'],
        ['label' => 'Account Types', 'route' => 'account-types.index', 'active' => 'account-types.*', 'can' => 'setup.view'],
    ]],
];
