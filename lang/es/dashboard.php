<?php

return [
    'title' => 'Dashboard operativo',
    'description' => 'Centro de control operativo para administración diaria.',
    'setup_title' => 'Configura tu sistema (:completed/:total)',
    'setup_description' => 'Completa estos pasos para evitar un dashboard vacío y activar operación diaria.',
    'dismiss_onboarding' => 'Ocultar por ahora',
    'progress_aria' => 'Progreso del checklist inicial',
    'recommended' => 'Recomendados',
    'income_month' => 'Ingresos operativos del mes',
    'income_hint' => 'Allocations (sin depósitos)',
    'expense_month' => 'Egresos del mes',
    'overdue_portfolio' => 'Cartera vencida total',
    'overdue_hint' => 'Contratos con renta vencida',
    'active_contracts' => 'Contratos activos',
    'units' => 'Unidades',
    'occupied_available' => ':occupied ocupadas / :available disponibles',
    'overdue_top10' => 'Vencidos (top 10)',
    'overdue_days' => 'Días atraso',
    'no_overdue' => 'Sin contratos vencidos.',
    'grace_top10' => 'En gracia (top 10)',
    'due_grace' => 'Vence / gracia',
    'grace_until' => 'Gracia: :date',
    'no_grace' => 'Sin contratos en gracia.',
    'recent_payments' => 'Pagos recientes (top 10)',
    'no_recent_payments' => 'Sin pagos recientes.',
    'flash' => [
        'checklist_hidden' => 'Checklist oculto hasta :date.',
        'month_closed' => 'El mes :month está cerrado. No se pueden generar rentas.',
        'rents_generated' => 'Rentas del :month: creadas=:created omitidas=:skipped.',
    ],
    'onboarding' => [
        'properties' => [
            'title' => 'Crear inmueble',
            'description' => 'Registra tu primer inmueble para empezar a operar.',
            'cta_properties' => 'Ir a propiedades',
            'cta_new_property' => 'Nuevo inmueble',
        ],
        'units' => [
            'title' => 'Crear unidades',
            'description' => 'Define unidades ocupables para poder contratar.',
            'cta_manage' => 'Gestionar unidades',
        ],
        'tenants' => [
            'title' => 'Crear inquilinos',
            'description' => 'Captura al menos un inquilino activo.',
            'cta_tenants' => 'Ir a inquilinos',
        ],
        'contracts' => [
            'title' => 'Crear contratos activos',
            'description' => 'Necesitas un contrato activo para generar rentas y cobranza.',
        ],
        'rent_charges' => [
            'title' => 'Generar o confirmar rentas del mes',
            'description' => 'Valida que existan cargos RENT para :month.',
            'cta_generate' => 'Generar rentas del mes',
        ],
        'payments' => [
            'title' => 'Registrar primer pago',
            'description' => 'Recomendado para validar recibo, allocation y cobranza.',
        ],
        'expenses' => [
            'title' => 'Registrar primer egreso',
            'description' => 'Recomendado para validar reporte de flujo y neto.',
        ],
    ],
];
