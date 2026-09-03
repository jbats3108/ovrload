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
| A1. Include block shared profile in referenced IDs | `ExerciseProfileService::profileIdsReferencedBy()` | Add `RoutineBlock.shared_exercise_profile_id` so archived profiles still appear in editor/Preferences picker when referenced only at block level |
| A2. In-progress workout guard | `WorkoutService` | Extract `assertInProgress(Workout)`; replace ~8 duplicate checks |
| A3. `completeDropset()` visibility | `WorkoutService` | `private`; only called from `completeSet()` |
| A4. Exercise-profile route authorization | `routes/settings.php`, `ExerciseProfilePolicy`, controllers | Add policy abilities (`sync`, `archive`, …); wire `->can()` on routes; keep service asserts as defense-in-depth |
| A5. Archive policy semantics | `ArchiveExerciseProfileController` | Use dedicated `archive` ability, not `delete` |

**Tests:** extend `ExerciseProfileServiceTest` for A1; existing feature tests cover A4/A5.

---

## Slice B — DRY helpers (no product change)

**Goal:** Centralise repeated logic from the profiles pivot. ~1 day.

| Task | Files | Notes |
|------|-------|-------|
| B1. Fingerprint / assignment helper | New `ExerciseProfileAssignment` (or static helper on existing service) | Unify branching in `RoutineEditorService`, `ExerciseProfileService::syncProfile` |
| B2. Deferred exercise picker payload | New builder; `EditRoutineController`, `PlayWorkoutController`, optionally `IndexAdminExercisesController` | Shared `ExercisePickerOptions::deferFor(User)` |
| B3. Published profiles query | `ExerciseProfileService` | Private `publishedProfilesFor(User, ?includeArchivedIds)` for `pageDataFor`, `optionsForUser`, `optionsForRoutineEditor` |
| B4. Routine structure eager-load | Constant or scope on `Routine` | Reuse graph in `WorkoutService`, `RoutineEditorPageData`, `RoutineDuplicator`, `HistoricalCreatePageData` |

---

## Slice C — Workout logging DRY

**Goal:** One code path for set values (play + history). ~1 day.

| Task | Files | Notes |
|------|-------|-------|
| C1. `WorkoutSetLogger` (or similar) | New class; `WorkoutService`, `WorkoutHistoryService` | `applyLoggedValues(WorkoutSet, reps, weight?, segments?, plateStack?, completedAt?)` |
| C2. Tests | Feature tests for play complete + history edit | Lock behaviour before/after |

---

## Slice D — Service seams (design choice required)

**Goal:** Split god services without changing public API surface initially.

### D1. `WorkoutService` (~980 lines)

| Seam | Methods |
|------|---------|
| Snapshot | `snapshotRoutineOntoWorkout`, historical snapshot helpers |
| Session | `completeSet`, `addWorkingSet`, `skipRestOfBlock`, dropset promote/demote, ad-hoc block |
| Lifecycle | `createWorkout`, `finishWorkout`, `discardWorkout`, `createHistoricalWorkout`, `inProgressFor` |

**Options:** (A) `WorkoutSnapshotService` + `WorkoutSessionService` + thin facade, or (B) private collaborator classes inside one service.

### D2. `ExerciseProfileService` (~750 lines)

| Seam | Methods |
|------|---------|
| Admin presets | `createPreset`, `updatePresetDraft`, `publishPreset`, … |
| User customs | `createCustom`, `archive`, `delete`, `restore`, … |
| Assignment | `syncProfile`, `staleAssignmentCountsForUser`, `assignedRoutinesByProfileId` |

---

## Slice E — Read path unification

**Goal:** Reduce routine ↔ workout DTO duplication.

| Task | Notes |
|------|-------|
| E1. Shared routine block reader | `RoutineBlockStructureData::fromRoutineBlock()` used by `RoutineEditorPageData` and `HistoricalCreateBlockData` |
| E2. Defer block-tree writers | `RoutineEditorService`, `RoutineDuplicator`, `WorkoutService::snapshotRoutineOntoWorkout` stay separate (different tables) |

**Pattern to follow:** `HistoryDetailPageData` already wraps `WorkoutPlayerPageData` — extend that reuse model upward for routine reads.

---

## Slice F — Auth, perf, lifecycle

| Task | Files | Notes |
|------|-------|-------|
| F1. Scoped workout route bind | `AppServiceProvider` | Match routine bind: owner-scoped ULID → 404 not 403 |
| F2. `syncProfile()` routine filter | `ExerciseProfileService` | `whereHas` blocks/exercises referencing profile id instead of all routines |
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

## Suggested PR order

1. **A** — merge first (bug + auth)
2. **B** — DRY helpers
3. **C** — set logger (when next touching workouts/history)
4. **D/E/F** — pick one seam per PR; do not batch

Update `docs/plan.md` **Shipped** when slices land; add open slices to **Code quality & security** backlog until done.
