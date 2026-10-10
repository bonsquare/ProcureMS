<?php

// The master_user has every permission. A sub_master gets the permission families of the work areas on its
// checklist (see App\Support\SubMasterAccess), plus dashboard.view and reports.view.
return [
    'roles' => [
        'school_admin' => ['*'],
        'school_head' => ['dashboard.view', 'procurement.view', 'procurement.approve', 'budget.view', 'aip.view', 'aip.approve', 'planning.view', 'liquidation.view', 'liquidation.approve', 'reports.view', 'organization.settings'],
        'administrative_officer' => ['dashboard.view', 'procurement.*', 'supplier.*', 'liquidation.view', 'reports.view'],
        'budget_officer' => ['dashboard.view', 'budget.*', 'aip.*', 'planning.*', 'accounting.manage', 'procurement.view', 'liquidation.view', 'reports.view'],
        'procurement_officer' => ['dashboard.view', 'procurement.*', 'supplier.*', 'planning.view', 'budget.view', 'reports.view'],
        'bac_user' => ['dashboard.view', 'procurement.view', 'procurement.approve', 'supplier.view', 'reports.view'],
        'accounting_officer' => ['dashboard.view', 'accounting.*', 'liquidation.*', 'budget.view', 'reports.view'],
        'cashier' => ['dashboard.view', 'cash.*', 'accounting.view', 'liquidation.view', 'reports.view'],
        'inspector' => ['dashboard.view', 'procurement.view', 'inspection.*', 'reports.view'],
        'auditor' => ['dashboard.view', 'procurement.view', 'budget.view', 'aip.view', 'planning.view', 'accounting.view', 'cash.view', 'liquidation.view', 'reports.view'],
        'viewer' => ['dashboard.view', 'procurement.view', 'budget.view', 'aip.view', 'planning.view', 'liquidation.view', 'reports.view'],
        'office_user' => ['dashboard.view', 'procurement.view', 'procurement.create', 'budget.view', 'liquidation.view'],
        'encoder' => ['dashboard.view', 'procurement.view', 'procurement.create', 'procurement.edit', 'supplier.*', 'liquidation.create', 'liquidation.view'],
        'approver' => ['dashboard.view', 'procurement.view', 'procurement.approve', 'liquidation.view', 'liquidation.approve', 'reports.view'],
        'liquidation_officer' => ['dashboard.view', 'liquidation.*', 'procurement.view', 'budget.view', 'reports.view'],
    ],
];
