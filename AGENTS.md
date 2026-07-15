# Repository Agent Operating Contract

**Status:** Active operational instructions

This file governs AI-assisted review, documentation, architecture, implementation, and GitHub work in this repository.

It is an operational contract for contributors and automated agents. It does not replace the package's public Runtime contract, the canonical Package Reference, or an explicit Owner decision.

---

## 1. Mandatory Start of Every Task

Before reviewing, changing, approving, or merging anything:

1. Fetch the latest remote `main` state and record its exact SHA.
2. Fetch the exact PR or branch state when one is involved:
   - base branch and base SHA;
   - head branch and head SHA;
   - open/closed state;
   - Draft/Ready state;
   - mergeability;
   - changed-file list;
   - actual diff;
   - current checks and workflow runs.
3. Read the governing documents in the order defined below.
4. Classify every affected contract as one of:
   - published and protected;
   - current implementation detail;
   - superseded post-release experiment;
   - proposed future contract;
   - historical or archived material.
5. Never rely on an earlier chat summary, a local branch, a previous PR body, or memory as proof of the current repository state.

When current state cannot be verified, do not invent it. Continue the audit as far as evidence allows and report the exact verification gap.

---

## 2. Documentation and Decision Authority Order

Use the following order when documents or statements appear to conflict.

### 2.1 Explicit Owner decisions

An explicit Owner decision is authoritative for the work it addresses.

For lasting architectural or compatibility decisions, the decision must be recorded in the repository and aligned across all affected active documents before Runtime implementation begins.

An Owner decision does not silently break an already published stable contract. A breaking change to a released contract requires an explicitly approved compatibility strategy and, when applicable, a meaningful major release.

### 2.2 Canonical stable package contract

The single canonical stable package contract is:

```text
EVENT_LOGGING_PACKAGE_REFERENCE.md
```

Use it with released tags and `CHANGELOG.md` to determine the published public Runtime surface and stable guarantees.

A public PHP symbol is not automatically a protected compatibility contract merely because it exists in the repository. Protection comes from the published release contract and its recorded stable inventory.

### 2.3 Current `main` Runtime evidence

Use the latest `main` code, schema, Composer metadata, and tests to determine the factual current implementation state.

If current code contradicts the published stable contract, treat that as a defect or documentation inconsistency. Do not let accidental implementation drift silently redefine the stable contract.

### 2.4 Repository standards

Documents under:

```text
docs/standards/
```

govern new code, unpublished APIs, package construction, Composer configuration, CI, naming, DTO shape, persistence use, testing, and presentation.

Standards govern how new work is built. They do not retroactively erase published compatibility guarantees. A documented legacy exception remains protected for its approved major line until separately changed.

### 2.5 Approved architecture and Owner-decision documents

Documents under:

```text
docs/architecture/
```

define approved designs, domain boundaries, rebuild contracts, implementation constraints, and recorded Owner decisions.

An architecture document marked proposed or blocked is not implemented Runtime merely because the document exists.

A domain-specific approved decision overrides older generic or historical wording for that domain, but all active documents must be updated so the repository does not retain competing instructions.

### 2.6 Roadmaps

Documents under:

```text
docs/roadmap/
```

define sequencing, status, dependencies, and future work.

A roadmap does not redefine a stable public contract and does not override an approved architecture contract.

### 2.7 Audits and inventories

Documents under:

```text
docs/audits/
```

record evidence, snapshots, file inventories, compatibility findings, and review history.

They are evidence sources, not independent design authorities. Their dates and audited SHAs must be checked before use.

### 2.8 Integration guides, domain READMEs, and examples

Integration guides, domain READMEs, and examples explain usage. They must follow the canonical contract and active architecture.

They do not create a stable API by themselves.

### 2.9 Archived documents, old PR bodies, and comments

Archived documents, historical PR descriptions, review comments, and closed-task instructions are historical evidence only.

They must never override current merged repository documents or current GitHub state.

---

## 3. Conflict-Resolution Rules

When active sources conflict:

1. Identify the exact conflicting statements and their files.
2. Determine whether the conflict concerns:
   - published compatibility;
   - factual current Runtime behavior;
   - future architecture;
   - work sequencing;
   - historical evidence.
3. Apply the authority order in Section 2.
4. Do not implement Runtime while an active architectural contradiction remains.
5. Correct the primary governing document itself. Do not rely only on a PR description, comment, or a separate note that says it supersedes an unchanged contradictory blueprint.
6. Update every affected active document in the same documentation change when practical:
   - blueprint;
   - Owner-decision document;
   - roadmap;
   - documentation inventory;
   - canonical Package Reference when the stable public contract changes;
   - integration documentation when usage changes.
7. Archive or explicitly mark obsolete documents instead of leaving two active sources of truth.

---

## 4. Contract Classification Before Rebuild Work

Every rebuild must separate three categories.

### 4.1 Protected published contract

Protect the released contract, including every applicable:

- public interface and method signature;
- public constructor parameter name, order, type, and default;
- DTO serialized keys and ordering;
- query filters and validation semantics;
- cursor activation and ordering behavior;
- limit normalization;
- row hydration and fallback behavior;
- exception class, boundary, message prefix, and previous throwable handling;
- schema and index behavior where compatibility depends on it;
- write-side policy behavior;
- fail-open or fail-closed reliability boundary;
- factory, provider, or bootstrap behavior included in the released contract.

Internal refactoring is allowed only when complete Regression and Integration evidence proves observable behavior is unchanged.

### 4.2 Superseded post-release experiment

An artifact introduced after the protected release and explicitly classified as a superseded experiment is a remediation input, not automatically a compatibility target.

The rebuild is not required to preserve its:

- interface shape;
- service shape;
- constructor;
- page or cursor DTO;
- serialization;
- cursor-generation approach;
- wrapper pagination semantics;
- internal coupling.

The existence of tests for a superseded experiment does not freeze that experiment. Those tests are replaced by tests for the approved contract and protected Regression behavior.

### 4.3 Proposed future contract

A proposed blueprint defines future work only.

It becomes implemented Runtime only after:

- explicit approval;
- a separate Runtime task or PR;
- complete implementation;
- all required verification gates;
- documentation status updates.

---

## 5. Atomic Rebuild Rule

Unless an explicit Owner decision says otherwise, a rebuild that replaces a superseded post-release experiment is atomic at the package level:

1. Add the approved replacement API and implementation.
2. Add complete Unit coverage for the new contract.
3. Add Regression coverage for every protected published behavior.
4. Add strict real-database Integration coverage when persistence is involved.
5. Preserve the protected release contract.
6. Delete the exact superseded Runtime and test artifacts in the same Runtime change set.
7. Remove package-owned references that present the deleted artifacts as usable API.
8. Update the Package Reference, integration docs, roadmap, inventory, and changelog as applicable.

Do not keep a superseded API as active or deprecated compatibility merely because it previously existed. Retaining it requires a new explicit Owner decision.

Maintained host repositories must be searched and any discovered usage migrated. Host search does not convert a superseded package artifact into a protected contract and does not postpone package-level deletion unless an explicit Owner decision establishes a different release strategy.

---

## 6. Admin Query Architecture Rules

For package-owned Admin Query work:

- Keep the Admin Query API separate from protected primitive read/query interfaces.
- Public interfaces and DTOs must be package-owned.
- Do not expose `maatify/persistence` classes through the public EventLogging API.
- Delegate generic page normalization, per-page clamping, offset calculation, ordering mechanics, count execution, `LIMIT`, `OFFSET`, and pagination metadata to the stable `maatify/persistence` API.
- Keep domain filters, trusted SQL, selected columns, parameter construction, mapper behavior, and exception translation inside the domain package boundary.
- Use one shared source of truth for filtered-count and data filters and parameters.
- Do not create a generic cross-domain repository or accept arbitrary column names or SQL expressions.
- Do not add host controllers, routes, authentication, permissions, UI, or framework wiring to the package.
- Use distinct named placeholders with native PDO. Reusing the same named placeholder in one SQL statement is prohibited.
- Preserve caller-owned transactions. Read repositories must not silently swallow storage failures.

---

## 7. Documentation Rules

- The root Package Reference is the only canonical stable package reference.
- New active documents must be registered in `docs/audits/DOCUMENTATION_INVENTORY.md` when they are part of the documented repository architecture.
- A blueprint must contain the complete coherent contract needed for implementation; do not scatter required rules across comments or PR descriptions.
- A separate Owner-decision document may clarify a decision, but the affected blueprint must also be aligned before approval.
- Temporary PR numbers, branch names, SHAs, Draft states, and stacked-merge steps must be identified as snapshots. Before merging, remove or update lifecycle instructions that have become false.
- PR descriptions are review aids, not long-term canonical documentation.
- Documentation-only PRs must not claim Runtime implementation, deletion, release, or Integration success.
- Historical documents must be archived or clearly marked historical.

---

## 8. Testing and Verification Truthfulness

Never claim a command or test was run unless there is direct evidence.

Always distinguish:

- run and passed;
- run and failed;
- skipped;
- not run;
- reported by another environment;
- unavailable due to missing infrastructure.

A skipped Integration job is not a passing Integration result.

For persistence rebuilds, the complete gate normally includes:

```text
composer validate --strict
composer analyse
composer test:unit
composer test:regression
strict real MySQL integration suite
git diff --check
```

Real MySQL Integration requirements must not be replaced by SQLite, mocks, or a silent skip when the approved blueprint requires native MySQL behavior.

CI status must be tied to the exact head SHA being approved or merged.

---

## 9. Git and Pull-Request Rules

- Do not merge without an explicit Owner instruction.
- Do not amend commits, force-push, rebase published review history, or rewrite a branch unless explicitly authorized.
- Use the expected head SHA when merging so a moved PR cannot be merged accidentally.
- Keep documentation approval and Runtime implementation in separate PRs when the approved process requires that separation.
- Keep changes inside the authorized file and domain scope.
- Do not smuggle schema, Composer, CI, or host-project changes into a documentation or domain-limited PR.
- For stacked PRs:
  1. record the exact base and head;
  2. after the base PR merges, retarget the child PR to `main`;
  3. recalculate the diff and mergeability;
  4. rerun or re-evaluate checks for the retargeted state;
  5. update stale merge-order wording before merging.
- After every merge, verify:
  - PR is closed and merged;
  - exact merge commit SHA;
  - latest `main` contains the intended files;
  - dependent PRs still target the correct base.

---

## 10. Required Review Report

Every final review or merge report must include, when applicable:

- repository;
- exact audited `main` SHA;
- PR number and URL;
- base branch and SHA;
- head branch and SHA;
- exact changed-file list;
- whether Runtime, tests, schema, Composer, CI, or docs changed;
- commands and jobs that actually ran;
- skipped or unrun gates;
- blocking findings;
- final decision: approved, needs correction, merged, or blocked;
- exact merge commit SHA after merge.

Do not describe a PR as correct merely because it is mergeable or because documentation-only CI passed.

---

## 11. Required Reading Order for Common Work

### Stable package contract review

1. `AGENTS.md`
2. `EVENT_LOGGING_PACKAGE_REFERENCE.md`
3. released tag and `CHANGELOG.md`
4. relevant current `src/`, schema, and tests
5. applicable standards
6. supporting active architecture and integration documents

### Admin Query rebuild review

1. `AGENTS.md`
2. `EVENT_LOGGING_PACKAGE_REFERENCE.md`
3. `docs/standards/PACKAGE_BUILDING_STANDARD.md`
4. `docs/architecture/ADMIN_QUERY_API_ARCHITECTURE.md`
5. relevant domain rebuild blueprint
6. relevant domain Owner-decision documents
7. `docs/roadmap/ADMIN_QUERY_API_ROADMAP.md`
8. relevant compatibility audits and documentation inventory
9. current domain Runtime, schema, and tests
10. maintained host usage search when integration impact is in scope

### Runtime implementation review

1. approved blueprint and Owner decisions
2. protected published contract
3. exact current `main` Runtime and tests
4. implementation diff
5. Unit and Regression evidence
6. strict real-database Integration evidence
7. static analysis and Composer validation
8. documentation and deletion inventory

---

## 12. Non-Negotiable Principle

Do not make repository decisions from convenience, precedent alone, or the accidental shape of unfinished code.

Protect what was actually published and approved. Rebuild what was explicitly classified as superseded. Implement only the reviewed contract. Report only what can be proven.