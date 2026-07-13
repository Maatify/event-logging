<?php
$path = 'docs/architecture/ADMIN_QUERY_AUDIT_TRAIL_POC_BLUEPRINT.md';
$content = file_get_contents($path);

// The previous user comment said: "The actual remote PR HEAD is still: 0c5f614e64bf1994bb5b16f064cd11e0719a13d7 ... the incorrect statement that no standards conflict exists."
// It also requested: "the standards-conflict result;"

$replacement = <<<MD
| Dependency Direction | `PACKAGE_BUILDING_STANDARD.md` | Outward dependencies only | Relies exclusively on core maatify deps | Architecture rules | No Conflict |
| Composer Impact | `PACKAGE_BUILDING_STANDARD.md` | `maatify/persistence` | Defined to add dependency safely | Composer require update | No Conflict |
| No `composer.lock` | `PACKAGE_BUILDING_STANDARD.md` | Lock must not be tracked | Lock omitted | Not committed | No Conflict |
| Unit Tests | `TESTING_STRATEGY.md` | Cover logic fully | Defined explicitly | Unit Test Matrix | No Conflict |
| Regression Tests | `TESTING_STRATEGY.md` | Prove V1 API preserved | Defined explicitly | Regression Matrix | No Conflict |
| MySQL Integration Tests| `TESTING_STRATEGY.md` | Cover DB Queries | Defined explicitly | Integration Matrix | No Conflict |
| PHPStan Max-Level | `PACKAGE_BUILDING_STANDARD.md` | Full types, no ignore | Adhering fully without suppressions | Strict DTO types | No Conflict |
| CI Compliance | `CI_WORKFLOW_STANDARD.md` | Automated execution | CI Gate commands required | Execution listed | No Conflict |
| Package-Reference Update | `LIBRARY_PRESENTATION_STANDARD.md`| Update documentation | Checked | Final PR requirement | No Conflict |
| Changelog Update | `LIBRARY_PRESENTATION_STANDARD.md`| Maintain structure | Checked | PR tracking | No Conflict |
| No Framework-Specific API | `PACKAGE_BUILDING_STANDARD.md` | Agnostic contracts | Clean implementation | No Controllers | No Conflict |
| Old-Artifact Retirement | `ADMIN_QUERY_API_ROADMAP.md` | Obsolete POC Removal | Exact file list added | Covered in retirement | No Conflict |

### Standards Conflict Discovered
**Conflict:** The `PACKAGE_BUILDING_STANDARD.md` rule `No generic cross-domain DTOs or query abstractions` (and Section 15 Read/Admin Query API Rules) directly forbids returning the exact persistence boundary abstractions (`PageResult`, `PageRequest`) to the caller. However, `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md` (and `docs/audits/ADMIN_QUERY_PHASE_1_RUNTIME_COMPATIBILITY_INVENTORY.md`) implied that `maatify/persistence` is the sole owner of generic pagination mechanics.
**Impact:** To satisfy both, the domain MUST wrap the persistence input/output types in its own specific `AuditTrailAdminQueryRequestDTO` and `AuditTrailAdminPageResultDTO`.
**Blueprint Decision:** Blocked at this decision until Owner approves. The blueprint proposes wrapping the persistence layer precisely within the domain adapter to avoid leaking generic page abstractions while relying on `maatify/persistence` internally.
MD;

$content = preg_replace(
    '/\| Dependency Direction.*?No Conflict \|\n.*?Old-Artifact Retirement.*?No Conflict \|/s',
    $replacement,
    $content
);

file_put_contents($path, $content);
