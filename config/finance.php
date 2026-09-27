<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Active Currency
    |--------------------------------------------------------------------------
    |
    | Supported currencies: USD and BDT.
    | This can also be updated dynamically from Admin Settings > Finance.
    |
    */

    'default_currency' => env('FINANCE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | Only USD ($) and Bangladeshi Taka (৳ BDT) are supported.
    |
    */

    'currencies' => [
        'USD' => [
            'code' => 'USD',
            'symbol' => '$',
            'name' => 'US Dollar',
            'locale' => 'en-US',
            'precision' => 2,
        ],
        'BDT' => [
            'code' => 'BDT',
            'symbol' => '৳',
            'name' => 'Bangladeshi Taka',
            'locale' => 'bn-BD',
            'precision' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Monthly Budget Target
    |--------------------------------------------------------------------------
    */

    'default_monthly_budget' => 3500.00,

    /*
    |--------------------------------------------------------------------------
    | Financial Categories
    |--------------------------------------------------------------------------
    |
    | Pre-configured income and expense categories with modern icons and colors.
    |
    */

    'categories' => [
        'income' => [
            [
                'id' => 'client_retainer',
                'name' => 'Client Retainer',
                'icon' => 'Briefcase',
                'color' => '#10b981',
            ],
            [
                'id' => 'product_sales',
                'name' => 'Product Sales',
                'icon' => 'ShoppingBag',
                'color' => '#059669',
            ],
            [
                'id' => 'consulting',
                'name' => 'Consulting & Services',
                'icon' => 'Code',
                'color' => '#34d399',
            ],
            [
                'id' => 'investments',
                'name' => 'Investments & Returns',
                'icon' => 'TrendingUp',
                'color' => '#6ee7b7',
            ],
            [
                'id' => 'other_income',
                'name' => 'Other Income',
                'icon' => 'CircleDollarSign',
                'color' => '#a7f3d0',
            ],
        ],
        'expense' => [
            [
                'id' => 'cloud_infra',
                'name' => 'Cloud & Infrastructure',
                'icon' => 'Server',
                'color' => '#6366f1',
            ],
            [
                'id' => 'software_saas',
                'name' => 'Software & SaaS',
                'icon' => 'Laptop',
                'color' => '#8b5cf6',
            ],
            [
                'id' => 'marketing',
                'name' => 'Marketing & Ads',
                'icon' => 'Megaphone',
                'color' => '#ec4899',
            ],
            [
                'id' => 'office',
                'name' => 'Office & Supplies',
                'icon' => 'Building2',
                'color' => '#f59e0b',
            ],
            [
                'id' => 'payroll',
                'name' => 'Contractors & Payroll',
                'icon' => 'Users',
                'color' => '#ef4444',
            ],
            [
                'id' => 'meals',
                'name' => 'Meals & Entertainment',
                'icon' => 'Utensils',
                'color' => '#f97316',
            ],
            [
                'id' => 'utilities',
                'name' => 'Utilities & Rent',
                'icon' => 'Home',
                'color' => '#06b6d4',
            ],
            [
                'id' => 'other_expense',
                'name' => 'Other Expenses',
                'icon' => 'Receipt',
                'color' => '#94a3b8',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    */

    'payment_methods' => [
        'card' => 'Credit / Debit Card',
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'bkash_nagad' => 'Mobile Banking (bKash / Nagad)',
        'other' => 'Other / Online',
    ],

];
