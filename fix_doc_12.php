<?php
$f2 = 'docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md';
$c2 = file_get_contents($f2);

$c2 = str_replace(
    '**Current Phase:** Phase 4: Runtime implemented and `DeliveryOperations` blueprint/design next',
    '**Current Phase:** Phase 4: DiagnosticsTelemetry Runtime implemented, DeliveryOperations blueprint/design next',
    $c2
);

$c2 = preg_replace(
    '/- `DiagnosticsTelemetry` Runtime is implemented;.*/s',
    "- DiagnosticsTelemetry Runtime is implemented;\n- DeliveryOperations blueprint/design and Owner approval are next;\n- DeliveryOperations Runtime is not yet authorized;\n- reporting/dashboard remains blocked;\n- no release or tag is authorized.",
    $c2
);

$c2 = str_replace('- `DiagnosticsTelemetry`: Runtime implemented', '- `DiagnosticsTelemetry`: Runtime implemented', $c2);

file_put_contents($f2, $c2);
