<?php

return [
    'roles' => [
        'Admin' => [
            'label' => 'Admin',
            'description' => 'Control total del sistema y configuración organizacional.',
        ],
        'Capturista' => [
            'label' => 'Capturista',
            'description' => 'Opera captura diaria de pagos y egresos con acceso operativo.',
        ],
        'Lectura' => [
            'label' => 'Lectura',
            'description' => 'Consulta información sin permisos de creación o administración.',
        ],
    ],
    'modules' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'permissions' => [
                'dashboard.view' => 'Ver dashboard',
            ],
        ],
        [
            'key' => 'cobranza',
            'label' => 'Cobranza',
            'permissions' => [
                'cobranza.view' => 'Ver cobranza',
            ],
        ],
        [
            'key' => 'catalogs',
            'label' => 'Catálogos',
            'permissions' => [
                'properties.view' => 'Ver propiedades',
                'properties.manage' => 'Gestionar propiedades',
                'units.view' => 'Ver unidades',
                'units.manage' => 'Gestionar unidades',
                'plazas.manage' => 'Gestionar plazas',
                'tenants.view' => 'Ver inquilinos',
                'tenants.manage' => 'Gestionar inquilinos',
                'documents.view' => 'Ver documentos',
                'documents.upload' => 'Subir documentos',
                'documents.delete' => 'Eliminar documentos',
            ],
        ],
        [
            'key' => 'contracts',
            'label' => 'Contratos y cargos',
            'permissions' => [
                'contracts.view' => 'Ver contratos',
                'contracts.manage' => 'Gestionar contratos',
                'contracts.settle' => 'Finiquitar contratos',
                'charges.view' => 'Ver cargos',
                'charges.manage' => 'Gestionar cargos',
                'rents.generate' => 'Generar rentas',
                'penalties.run' => 'Ejecutar multas',
            ],
        ],
        [
            'key' => 'payments',
            'label' => 'Pagos y recibos',
            'permissions' => [
                'payments.view' => 'Ver pagos',
                'payments.create' => 'Registrar pagos',
                'receipts.send' => 'Compartir recibos',
            ],
        ],
        [
            'key' => 'expenses',
            'label' => 'Egresos',
            'permissions' => [
                'expenses.view' => 'Ver egresos',
                'expenses.create' => 'Registrar egresos',
                'expenses.manage' => 'Gestionar egresos',
                'expense_categories.manage' => 'Gestionar categorías de egreso',
            ],
        ],
        [
            'key' => 'reports',
            'label' => 'Reportes',
            'permissions' => [
                'reports.view' => 'Ver reportes',
                'reports.export' => 'Exportar reportes',
            ],
        ],
        [
            'key' => 'month_close',
            'label' => 'Cierres',
            'permissions' => [
                'month_close.view' => 'Ver cierres mensuales',
                'month_close.close' => 'Cerrar mes',
                'month_close.reopen' => 'Reabrir mes',
            ],
        ],
        [
            'key' => 'audit',
            'label' => 'Auditoría y usuarios',
            'permissions' => [
                'audit.view' => 'Ver auditoría',
                'audit.export' => 'Exportar auditoría',
                'users.manage' => 'Gestionar usuarios',
                'invitations.manage' => 'Gestionar invitaciones',
            ],
        ],
        [
            'key' => 'system',
            'label' => 'Sistema',
            'permissions' => [
                'settings.manage' => 'Gestionar configuración',
                'system.view' => 'Ver estado del sistema',
            ],
        ],
    ],
];
