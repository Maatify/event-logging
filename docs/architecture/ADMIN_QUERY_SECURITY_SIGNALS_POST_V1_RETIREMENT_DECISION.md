# SecuritySignals Post-v1 Wrapper Retirement Decision

**Status:** Owner Decision / Required by SecuritySignals Rebuild

This document records the retirement rule for the SecuritySignals pagination artifacts introduced after `v1.0.0`.

It applies to the SecuritySignals Admin Query rebuild documented by PR #102 and supersedes any wording that delays package-level deletion of the superseded wrapper until a later host-migration or cleanup phase.

---

## 1. Protected Compatibility Boundary

Only the published `v1.0.0` SecuritySignals Runtime contract is protected.

The protected boundary includes:

- `SecuritySignalsQueryInterface::find()`;
- `SecuritySignalsQueryDTO`;
- `SecuritySignalsViewDTO`;
- the primitive MySQL query repository constructor and observable behavior;
- primitive filters and primitive cursor activation semantics;
- primitive ordering and limit behavior;
- primitive row hydration and storage exception boundaries;
- the SecuritySignals schema;
- write-side policy behavior;
- recorder fail-open behavior.

The required distinct-placeholder correction inside the primitive cursor SQL remains behavior-preserving and does not expand the protected surface.

---

## 2. Superseded Post-v1 Artifacts

The following Runtime files were introduced after `v1.0.0` and are outside the frozen compatibility boundary:

```text
src/SecuritySignals/Contract/SecuritySignalsPaginatedQueryInterface.php
src/SecuritySignals/DTO/SecuritySignalsQueryCursorDTO.php
src/SecuritySignals/DTO/SecuritySignalsQueryPageDTO.php
src/SecuritySignals/Service/SecuritySignalsPaginatedQueryService.php
```

Their directly associated tests are:

```text
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryCursorDTOTest.php
tests/Unit/SecuritySignals/DTO/SecuritySignalsQueryPageDTOTest.php
tests/Unit/SecuritySignals/Service/SecuritySignalsPaginatedQueryServiceTest.php
```

These seven files are classified as:

```text
Superseded Post-v1 Experiment
```

They are remediation inputs, not compatibility contracts.

---

## 3. Contracts That Must Not Be Preserved

The rebuild has no obligation to preserve the superseded wrapper's:

- public interface shape;
- service shape;
- constructor signatures;
- page DTO contract;
- cursor DTO contract;
- serialized page or cursor keys;
- cursor-generation approach;
- wrapper pagination semantics;
- internal coupling to the primitive query path.

The existence of public PHP symbols in this post-v1 experiment does not make them part of the protected `v1.0.0` package surface.

The replacement contract is the separately approved package-owned SecuritySignals Admin Query API using `maatify/persistence`.

---

## 4. Deletion Is Part of the Rebuild

The exact seven superseded Runtime/test files must be deleted in the same SecuritySignals Runtime rebuild change set that introduces and verifies their replacement.

Deletion is not deferred to:

- a later cleanup PR;
- a later package release phase;
- a post-rebuild host-migration gate;
- preservation of the old cursor wrapper for transitional compatibility.

The Runtime rebuild is atomic at the package level:

1. add the approved SecuritySignals Admin Query implementation;
2. add its Unit, Regression, and strict real-MySQL Integration coverage;
3. preserve the protected `v1.0.0` primitive behavior;
4. delete the exact seven superseded post-v1 artifacts;
5. update package and integration documentation.

A Runtime PR that keeps the superseded wrapper as an active or deprecated compatibility layer is incomplete unless a new explicit Owner decision authorizes that exception.

---

## 5. Host Repository Handling

Maintained host repositories must still be searched because this is a public library.

The known current state is that the superseded SecuritySignals wrapper has not been fully adopted by any host project.

Any discovered usage must be replaced with the approved Admin Query API in the relevant host project.

However, host search and host migration do not convert the superseded package artifacts into protected contracts and do not postpone their deletion from the package rebuild.

Host migration work may be delivered through separate host-repository PRs, coordinated with the package version that contains the rebuilt API.

---

## 6. Required Runtime Verification

The Runtime rebuild that removes the superseded artifacts must prove:

- the protected primitive public API remains unchanged;
- the primitive cursor still activates only when both cursor values are present;
- primitive ordering and limit behavior remain unchanged;
- distinct native-PDO cursor placeholders are used;
- primitive hydration and exception behavior remain unchanged;
- the new Admin Query contract passes complete Unit coverage;
- primitive behavior passes Regression coverage;
- the Admin and primitive paths pass strict real-MySQL Integration coverage;
- no package-owned Runtime reference to any deleted artifact remains;
- documentation and examples no longer present the superseded wrapper as usable API.

---

## 7. Precedence

This decision supersedes any statement in the SecuritySignals rebuild blueprint or PR description that says or implies:

- the seven artifacts must remain until after host search or migration;
- deletion belongs to a later cleanup phase;
- the old page/cursor contracts must be preserved;
- the superseded cursor-wrapper behavior is part of the protected `v1.0.0` contract.

The correct rule is:

```text
Protected v1.0.0 primitive contract: preserve.
Superseded post-v1 pagination wrapper: rebuild and delete atomically.
Host usage: search and migrate, without preserving the obsolete package API.
```

---

## 8. Authorization Boundary

This documentation decision does not itself implement Runtime changes.

It defines the mandatory deletion and compatibility scope for the future SecuritySignals Runtime rebuild PR.
