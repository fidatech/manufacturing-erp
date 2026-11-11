<?php

/*
| What a role can be allowed to do. Every module has two switches:
| "view" (open and read) and "manage" (add, change, cancel).
| The Administrator role always has everything.
*/

return [
    'production' => 'Manufacturing: recipes and production batches',
    'lab' => 'Laboratory and quality control',
    'sales' => 'Sales, quotations, deliveries and returns',
    'purchases' => 'Purchases, orders, goods received and returns',
    'inventory' => 'Stock, transfers and physical count',
    'items' => 'Products and raw materials',
    'accounts' => 'Customers, suppliers, banks and all other accounts',
    'money' => 'Money in, money out, expenses, exchange, capital and loans',
    'journal' => 'Day book (roznamcha) and journal entries',
    'assets' => 'Assets and depreciation',
    'hr' => 'Employees and payroll',
    'reports' => 'Reports',
    'documents' => 'Documents',
    'setup' => 'Setup: currencies, rates, warehouses, units, company',
    'users' => 'Users and roles',
    'audit' => 'Audit log',
];
