# Backend tidy plan

Refactoring backlog after the exercise-profiles pivot and related feature growth. Domain language stays in `CONTEXT.md`; profile semantics in [ADR-0007](../adr/0007-exercise-profiles-as-copied-recipes.md).

**Branch:** `refactor/backend-tidy`  
**Delivery:** atomic commits per slice below; one slice = one reviewable PR commit (or small PR stack).

---

## Intentionally kept (not refactor targets)

| Item | Why |
|------|-----|
| Dual FKs (`shared_exercise_profile_id`, `exercise_profile_id`, fingerprints) | ADR-0007 copied recipes |
| `RoutineEditorService` delete-all-blocks on save | Position-based workout matching; block IDs change each save |
| Legacy user training columns | Still read/written until profiles fully own defaults |
| Published presets via `ExerciseProfileSeeder` + JSON | Idempotent migration/seed path for fresh DBs |
| `warmUpSetGroup()` / `workingSetGroup()` on block models | Live in `WorkoutService`; prod uses `setGroups` + type elsewhere |

---

## Slice A — Correctness + hygiene (no product change)

**Goal:** Fix one real bug and low-risk cleanup. ~half day.

| Task | Files | Notes |
|------|-------|-------|
| A1. Include block shared profile in referenced IDs | `ExerciseProfileAssignmentService::profileIdsReferencedBy()` | **Done (by design)** — picker refs exercise + routine default; shared-only archived stays excluded (`options_for_routine_editor_ignores_archived_profiles_only_on_shared_assignment`) |
| A2. In-progress workout guard | `WorkoutSessionService` | **Done** — `assertInProgress(Workout)` used across session mutations |
| A3. `completeDropset()` visibility | `WorkoutSessionService` | **Done (n/a)** — dropset path is `promoteToDropset` / `demoteFromDropset`; no separate `completeDropset` |
| A4. Exercise-profile route authorization | `routes/settings.php`, `ExerciseProfilePolicy`, controllers | **Done** — policy `create` / `sync` / `archive`; `->can()` on settings routes; controller Gate + service asserts |
| A5. Archive policy semantics | `ArchiveExerciseProfileController` | **Done** — uses `archive` ability, not `delete` |

**Tests:** `ExerciseProfileServiceTest` (A1 design); `ExerciseProfilePolicyTest` + feature 403s cover A4/A5.

---

## Slice B — DRY helpers (no product change)

**Goal:** Centralise repeated logic from the profiles pivot. ~1 day.

| Task | Files | Notes |
|------|-------|-------|
| B1. Fingerprint / assignment helper | `ExerciseProfileAssignment` | **Done** — used by writers + assignment sync |
| B2. Deferred exercise picker payload | `ExercisePickerOptions::deferFor(User)` | **Done** — `EditRoutineController`, `PlayWorkoutController` |
| B3. Published profiles query | `ExerciseProfileService` | Private `publishedProfilesFor(User, ?includeArchivedIds)` for `pageDataFor`, `optionsForUser`, `optionsForRoutineEditor` |
| B4. Routine structure eager-load | `Routine::EDITOR_STRUCTURE` (+ `_WITH_DELOAD`) | **Done** — editor, duplicator, historical create, workout paths |

---

## Slice C — Workout logging DRY — **done**

**Goal:** One code path for set values (play + history). ~1 day.

| Task | Files | Notes |
|------|-------|-------|
| C1. `WorkoutSetLogger` | `WorkoutSetLogger`, session/history/snapshot | **Done** — `applyLoggedValues(...)` |
| C2. Tests | Feature tests for play complete + history edit | **Done** |

---

## Slice D — Service seams (design choice required)

**Goal:** Split god services without changing public API surface initially.

### D1. `WorkoutService` — **done**

| Seam | Module |
|------|--------|
| Snapshot | `WorkoutSnapshotService` |
| Session | `WorkoutSessionService` |
| Lifecycle | thin `WorkoutService` facade (~408 lines) |

### D2. `ExerciseProfileService` — **partial**

| Seam | Module |
|------|--------|
| Admin presets | `ExerciseProfilePresetService` |
| Assignment | `ExerciseProfileAssignmentService` |
| User customs / page data | still on `ExerciseProfileService` (~405 lines) |

### D3. `RoutineEditorService` — **done**

| Seam | Module |
|------|--------|
| Facade | `RoutineEditorService::sync` — lock, stale check, header, delete-all blocks, loop writer |
| Block tree orchestrate | `RoutineBlockWriter::create` — shape assert, block row, working/dropsets/warm-up set groups |
| Exercise persist | `RoutineBlockExerciseWriter::create` — prescription, fingerprints, floor/deload storage |
| Profile resolve/tamper | `RoutineBlockProfileGuard` — shared/exercise profile load + fingerprint value guards |
| Shape rules | `RoutineBlockShape::assert` / `assertCircuitExercise` — pure type constraints |

Public API unchanged. E2 still applies (do not merge writers with `RoutineDuplicator` / workout snapshot). Delete-all-blocks-on-save stays intentional.

---

## Slice E — Read path unification

**Goal:** Reduce routine ↔ workout DTO duplication.

| Task | Notes |
|------|-------|
| E1. Shared routine block reader | **Done** — `RoutineBlockStructureData::fromRoutineBlock()` used by `RoutineEditorPageData` and `HistoricalCreateBlockData` |
| E2. Defer block-tree writers | **Done (policy)** — editor / duplicator / workout snapshot stay separate (different tables) |

**Pattern to follow:** `HistoryDetailPageData` already wraps `WorkoutPlayerPageData` — extend that reuse model upward for routine reads.

---

## Slice F — Auth, perf, lifecycle

| Task | Files | Notes |
|------|-------|-------|
| F1. Scoped workout route bind | `AppServiceProvider` | **Done** — owner-scoped ULID → 404 not 403 |
| F2. `syncProfile()` routine filter | `ExerciseProfileAssignmentService` | `whereHas` blocks/exercises referencing profile id instead of all routines |
| F3. Progression session store | `WorkoutProgressionService`, finish/history controllers | Extract session adapter; remove duplicate `forgetSiblingProgressionSessions` in controllers |
| F4. Backfill out of seeders | `DatabaseSeeder`, `E2eSeeder` | **After** prod migration verified; keep migration + dedicated tests |

---

## Defer (explicitly out of scope)

- Incremental routine editor sync (replace delete-all-blocks) — high regression risk
- Re-add culled Eloquent inverse relations unless a query needs them
- Drop legacy user training columns — product + migration decision
- PHPStan typing items — tracked in `docs/plan.md` Code quality section
- Model niceties (`resolvedAchievementFloor`, `WorkoutSet::blockExercise()` relation)

---

## Remaining open

- **B3** — published profiles query helper
- **D2** — finish splitting user-custom / page-data off `ExerciseProfileService` if it grows again
- **F2** — filter `syncProfile` routines with `whereHas`
- **F3** — progression session adapter
- **F4** — backfill out of seeders (after prod verification)

## Suggested PR order

1. **A** — merge first (bug + auth) — largely shipped
2. **B** — DRY helpers — B1/B2/B4 shipped; B3 open
3. **C** — set logger — shipped
4. **D/E/F** — pick one seam per PR; do not batch

Update `docs/plan.md` **Shipped** when slices land; add open slices to **Code quality & security** backlog until done.
