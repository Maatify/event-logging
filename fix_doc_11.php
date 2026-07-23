<?php
$f2 = 'docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md';
$c2 = file_get_contents($f2);

$c2 = str_replace(
    '**Current Phase:** Phase 4: `DiagnosticsTelemetry` Blueprint Approved / Runtime Next',
    '**Current Phase:** Phase 4: DiagnosticsTelemetry Runtime implemented, DeliveryOperations blueprint/design next',
    $c2
);
$c2 = str_replace(
    '**Current Phase:** Phase 4: `DiagnosticsTelemetry` Runtime Next',
    '**Current Phase:** Phase 4: DiagnosticsTelemetry Runtime implemented, DeliveryOperations blueprint/design next',
    $c2
);
$c2 = preg_replace(
    '/- `DiagnosticsTelemetry` Runtime is next.*/s',
    "- DiagnosticsTelemetry Runtime is implemented;\n- DeliveryOperations blueprint/design and Owner approval are next;\n- DeliveryOperations Runtime is not yet authorized;\n- reporting/dashboard remains blocked;\n- no release or tag is authorized.",
    $c2
);
$c2 = str_replace('- `DiagnosticsTelemetry`: Runtime Next', '- `DiagnosticsTelemetry`: Runtime implemented', $c2);
file_put_contents($f2, $c2);


$f3 = 'docs/audits/DOCUMENTATION_INVENTORY.md';
$c3 = file_get_contents($f3);
$c3 = preg_replace(
    '/\| `docs\/architecture\/ADMIN_QUERY_API_ARCHITECTURE.md`      \| Architecture   \| .*? \|/',
    '| `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`      | Architecture   | Phase 3 Remediation Complete / Phase 4 Active / DiagnosticsTelemetry Runtime Implemented / DeliveryOperations Blueprint Next |',
    $c3
);
file_put_contents($f3, $c3);


$f4 = 'docs/integration/ADMIN_READ_USAGE.md';
$c4 = file_get_contents($f4);
$c4 = str_replace(
    'Currently, `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, `DiagnosticsTelemetry`, and `SecuritySignals` have completed rebuilt/replacement Admin Query API paths.',
    'Currently, `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, and `SecuritySignals` use rebuilt/replacement Admin Query paths. `DiagnosticsTelemetry` uses a new Admin Query implementation. `DeliveryOperations` blueprint/design and Owner approval remain next.',
    $c4
);
$c4 = str_replace(
    'Currently, `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, and `SecuritySignals` have completed rebuilt/replacement Admin Query API paths. `DiagnosticsTelemetry` uses a new approved Admin Query implementation. `DeliveryOperations` remains future blueprint/design work.',
    'Currently, `AuthoritativeAudit`, `AuditTrail`, `BehaviorTrace`, and `SecuritySignals` use rebuilt/replacement Admin Query paths. `DiagnosticsTelemetry` uses a new Admin Query implementation. `DeliveryOperations` blueprint/design and Owner approval remain next.',
    $c4
);
file_put_contents($f4, $c4);
