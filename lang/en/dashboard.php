<?php

return [
    'title' => 'Operational Dashboard',
    'description' => 'Operational command center for daily administration.',
    'setup_title' => 'Set up your system (:completed/:total)',
    'setup_description' => 'Complete these steps to avoid an empty dashboard and enable daily operations.',
    'dismiss_onboarding' => 'Hide for now',
    'progress_aria' => 'Initial checklist progress',
    'recommended' => 'Recommended',
    'income_month' => 'Operating income this month',
    'income_hint' => 'Allocations (excluding deposits)',
    'expense_month' => 'Expenses this month',
    'overdue_portfolio' => 'Total overdue portfolio',
    'overdue_hint' => 'Contracts with overdue rent',
    'active_contracts' => 'Active contracts',
    'units' => 'Units',
    'occupied_available' => ':occupied occupied / :available available',
    'overdue_top10' => 'Overdue (top 10)',
    'overdue_days' => 'Days overdue',
    'no_overdue' => 'No overdue contracts.',
    'grace_top10' => 'In grace period (top 10)',
    'due_grace' => 'Due / grace',
    'grace_until' => 'Grace until: :date',
    'no_grace' => 'No contracts in grace period.',
    'recent_payments' => 'Recent payments (top 10)',
    'no_recent_payments' => 'No recent payments.',
    'flash' => [
        'checklist_hidden' => 'Checklist hidden until :date.',
        'month_closed' => 'Month :month is closed. Rent charges cannot be generated.',
        'rents_generated' => 'Rent for :month: created=:created skipped=:skipped.',
    ],
    'onboarding' => [
        'properties' => [
            'title' => 'Create a property',
            'description' => 'Register your first property to start operating.',
            'cta_properties' => 'Go to properties',
            'cta_new_property' => 'New property',
        ],
        'units' => [
            'title' => 'Create units',
            'description' => 'Define leasable units before creating contracts.',
            'cta_manage' => 'Manage units',
        ],
        'tenants' => [
            'title' => 'Create tenants',
            'description' => 'Add at least one active tenant.',
            'cta_tenants' => 'Go to tenants',
        ],
        'contracts' => [
            'title' => 'Create active contracts',
            'description' => 'You need an active contract to generate rent and collections.',
        ],
        'rent_charges' => [
            'title' => 'Generate or confirm monthly rent',
            'description' => 'Verify RENT charges exist for :month.',
            'cta_generate' => 'Generate monthly rent',
        ],
        'payments' => [
            'title' => 'Record first payment',
            'description' => 'Recommended to validate receipts, allocations, and collections.',
        ],
        'expenses' => [
            'title' => 'Record first expense',
            'description' => 'Recommended to validate cash flow report and net totals.',
        ],
    ],
];
