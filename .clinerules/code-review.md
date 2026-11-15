---
description: Full-app and slice /code-review process — bugs, security, missing tests, FE double-submit
alwaysApply: false
---

# Code review (OVRLOAD)

Use when the user runs `/code-review`, asks for a domain slice review, or a full-app review pass. Read-only unless they ask for fixes. Base on `main` unless they name another base.

## Priority order for findings

1. Security (authz, invite bypass, mass assignment, IDOR)
2. Data corruption (progression, snapshot vs routine, history re-eval)
3. Behavioral bugs (Play, editor, settings)
4. Missing tests on the above
5. Consistency / ADR drift last

## Per-slice checklist

1. Routes + policies → ownership / IDOR
2. Services → rules vs `CONTEXT.md` + ADRs
3. Mutations → validation, race, soft-fail vs hard fail
4. Matching FE (`resources/js/{domain}/`) → client/server drift
5. **Double-submit / duplicate mutation** — for every user-triggered POST/PUT/DELETE:
   - Prefer Inertia `useForm` + `:disabled="form.processing"` (or equivalent busy flag) on the control that fires the request
   - Flag bare `router.post` / `router.put` / `router.delete` with no processing/disabled guard (Dashboard start/finish, Play finish/abandon/± set, Progression skip, history delete, admin revoke/delete, etc.)
   - Note whether the **server** also rejects duplicates (idempotent or 422) — FE guard alone is not enough for races
6. Tests → happy / fail / edge; note gaps

## Slice order (full app)

1. Workouts → 2. Routines → 3. Auth + Admin → 4. Settings + Users → 5. Shared + FE shell → 6. Cross-cutting

Findings only: severity → path → cause → fix. No drive-by refactors.
