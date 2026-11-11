<?php

return [
    'by' => 'by',

    'menu' => [
        'main' => 'Main',
        'dashboard' => 'Dashboard',
    ],

    'nav' => [
        'home' => 'Home',
        'menu' => 'Menu',
        'back' => 'Back',
        'search' => 'Search',
        'search_placeholder' => 'Search pages…',
        'no_results' => 'No results',
        'theme' => 'Theme',
        'toggle_theme' => 'Light / dark mode',
        'toggle_sidebar' => 'Show or hide the menu',
        'sign_out' => 'Sign out',
        'sign_out_question' => 'Sign out?',
        'sign_out_text' => 'You will need to sign in again to continue.',
    ],

    'actions' => [
        'cancel' => 'Cancel',
        'yes' => 'Yes',
        'confirm_title' => 'Are you sure?',
    ],

    'auth' => [
        'title' => 'Sign in',
        'welcome' => 'Welcome back',
        'subtitle' => 'Sign in to continue',
        'email' => 'Email address',
        'password' => 'Password',
        'remember' => 'Keep me signed in',
        'submit' => 'Sign in',
        'show_password' => 'Show password',
        'hero_kicker' => 'Purchases · Production · Sales',
        'hero_title' => 'Your factory, in one simple system.',
        'hero_text' => 'Record purchases, production batches and sales, and always know your stock.',
    ],

    'errors' => [
        'no_rate' => 'There is no exchange rate for :currency yet. Open Setup > Exchange rates and write how many :currency equal 1 :base.',
        'negative_amount' => 'An amount cannot be below zero.',
        'empty_entry' => 'Nothing to save: fill in at least two lines with amounts.',
        'unbalanced' => 'The debit and credit amounts are not equal.',
        'qty_positive' => 'The quantity of :item must be more than zero.',
        'not_enough_stock' => 'Not enough :item in :warehouse. In stock: :have, needed: :need.',
        'cancel_would_go_negative' => 'This cannot be cancelled: some of :item has already been sold or used.',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'empty_title' => 'Welcome, :name',
        'empty_text' => 'The system is ready. Its modules will appear in the menu as they are added.',
    ],
];
