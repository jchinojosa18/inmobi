<?php

return [
    'quick_search_aria' => 'Quick search',
    'placeholder' => 'Search contracts, units, tenants…',
    'confirm_action' => 'Confirm action:',
    'confirm_enter_hint' => 'Press :enter to run or :esc to cancel.',
    'actions_heading' => 'Actions',
    'confirm' => 'Confirm',
    'no_matching_actions' => 'No actions match ":query".',
    'min_chars_hint' => 'Type at least 2 characters to search contracts, units, tenants, or properties.',
    'no_entity_results' => 'No entity results for ":query".',
    'navigate' => 'Navigate',
    'execute' => 'Run',
    'close' => 'Close',
    'item' => '{1} :count item|[2,*] :count items',
    'contract_label' => 'Contract #:id · :name',
    'default_action_executed' => 'Action executed.',
    'navigating' => 'Navigating...',
    'type_labels' => [
        'contract' => 'Contracts',
        'tenant' => 'Tenants',
        'unit' => 'Units',
        'property' => 'Properties',
    ],
    'flash' => [
        'month_closed' => 'Month :month is closed. Rent charges cannot be generated.',
        'rents_generated' => 'Rent generated: :created created, :skipped skipped.',
    ],
    'actions' => [
        'register_payment' => [
            'label' => 'Record payment',
            'success_message' => 'Opening payment form...',
        ],
        'register_expense' => [
            'label' => 'Record expense',
            'success_message' => 'Opening expense form...',
        ],
        'new_contract' => [
            'label' => 'New contract',
            'success_message' => 'Navigating to New contract...',
        ],
        'new_property' => [
            'label' => 'New property',
            'success_message' => 'Navigating to New property...',
        ],
        'go_cobranza' => [
            'label' => 'Go to Collections',
            'success_message' => 'Navigating to Collections...',
        ],
        'go_contracts' => [
            'label' => 'Go to Contracts',
            'success_message' => 'Navigating to Contracts...',
        ],
        'go_flow_report' => [
            'label' => 'Cash flow report',
            'success_message' => 'Navigating to Cash flow report...',
        ],
        'generate_current_month_rent' => [
            'label' => 'Generate monthly rent',
        ],
    ],
];
