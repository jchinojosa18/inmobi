<?php

return [
    'quick_search_aria' => 'Búsqueda rápida',
    'placeholder' => 'Busca contratos, unidades, inquilinos…',
    'confirm_action' => 'Confirmar acción:',
    'confirm_enter_hint' => 'Presiona :enter para ejecutar o :esc para cancelar.',
    'actions_heading' => 'Acciones',
    'confirm' => 'Confirmar',
    'no_matching_actions' => 'No hay acciones que coincidan con ":query".',
    'min_chars_hint' => 'Escribe al menos 2 caracteres para buscar contratos, unidades, inquilinos o propiedades.',
    'no_entity_results' => 'Sin resultados de entidades para ":query".',
    'navigate' => 'Navegar',
    'execute' => 'Ejecutar',
    'close' => 'Cerrar',
    'item' => '{1} :count elemento|[2,*] :count elementos',
    'contract_label' => 'Contrato #:id · :name',
    'default_action_executed' => 'Acción ejecutada.',
    'navigating' => 'Navegando...',
    'type_labels' => [
        'contract' => 'Contratos',
        'tenant' => 'Inquilinos',
        'unit' => 'Unidades',
        'property' => 'Propiedades',
    ],
    'flash' => [
        'month_closed' => 'El mes :month está cerrado. No se pueden generar rentas.',
        'rents_generated' => 'Rentas generadas: creadas :created, omitidas :skipped.',
    ],
    'actions' => [
        'register_payment' => [
            'label' => 'Registrar pago',
            'success_message' => 'Abriendo registro de pago...',
        ],
        'register_expense' => [
            'label' => 'Registrar egreso',
            'success_message' => 'Abriendo registro de egreso...',
        ],
        'new_contract' => [
            'label' => 'Nuevo contrato',
            'success_message' => 'Navegando a Nuevo contrato...',
        ],
        'new_property' => [
            'label' => 'Nuevo inmueble',
            'success_message' => 'Navegando a Nuevo inmueble...',
        ],
        'go_cobranza' => [
            'label' => 'Ir a Cobranza',
            'success_message' => 'Navegando a Cobranza...',
        ],
        'go_contracts' => [
            'label' => 'Ir a Contratos',
            'success_message' => 'Navegando a Contratos...',
        ],
        'go_flow_report' => [
            'label' => 'Reporte de flujo',
            'success_message' => 'Navegando a Reporte de flujo...',
        ],
        'generate_current_month_rent' => [
            'label' => 'Generar rentas del mes',
        ],
    ],
];
