# Plan

Working backlog for OVRLOAD v2. Update this as items ship or get deferred. Domain language stays in `CONTEXT.md`; hard decisions stay in `docs/adr/`.

**Grill cleanup:** when a grilled feature ships, delete its `## Grill: …` section. Move any still-open deferred bullets into **Backlog**; do not keep decided implementation notes here.

**Notion inbox:** after pulling bullets from Notion [Ovrload](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) into this file, clear **only** the list items under `## Backlog:` — leave that header and a single empty bullet (`-`). Do not replace the whole page or delete child pages / other sections.

## Now

- **Play quick-confirm (Target hit / Floor hit)** — working sets: Target hit / Floor hit; warm-ups: Done (= target); Edit opens log sheet; dropsets + timed keep Done → sheet

## Backlog

Single triage list — reprioritize across buckets as needed. **Features (FAQ)** are listed on the public help/FAQ page for beta testers. Notion [inbox](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) → pull new bullets into the right bucket below.

-

### Features (FAQ)

Public order matches `/beta-tester-faqs`, including recently added items.

1. ~~**Exercise profiles**~~ — recently added
2. ~~**Add Historical Workouts**~~ — recently added
3. ~~**Custom user exercises**~~ — recently added
4. ~~**Add exercise in Play**~~ — recently added
5. ~~**Do groups later**~~ — recently added
6. ~~**Choose an alternate exercise for Deload sessions**~~ — recently added
7. **Gym dumbbell / rack inventory** — full rack range for run-the-rack / planning
8. **Viewable Progression Data** — charts/tables/export; large feature, own grill later
9. ~~**Circuit workouts**~~ — recently added
10. ~~**Skip a block and come back later in Play**~~ — shipped as **Do groups later**; kept on public FAQ as recently added
11. **Dropsets on supersets** — multi-segment dropsets inside a two-exercise superset round
12. ~~**Routine Progression Style**~~ — recently added

### Parked (internal — not on public FAQ)

- ~~**Stepper controls for reps and sets**~~ — shipped (clearable +/− on sets/reps/floor; validate on save)
- **Flexible block timing and entries** — allow circuit blocks with a single entry, or support time-based standard blocks instead of rep-based prescriptions; decide whether this needs a new block type
- **Strava integration** — OAuth / export / privacy grill later
- **Garmin sync** — after Strava
- **Ad-hoc / off-routine historical log (C2)** — log a lift not on a routine session; own grill (maybe after Play ad-hoc)
- **Benchmark exercises / 1RMs** — track reference lifts / estimated maxes
- **In-app product tour** — after the public `/tutorial` page; own grill
- **PT mode** — new user type; client roster; personal + client routines; PT→client share (includes client switching / former account switcher) — grill: [PT mode](#grill-pt-mode) (parked until after solo-lifter queue)
- **Exercise videos (PT)** — park until PT mode exists — grill: [Exercise videos](#grill-exercise-videos-pt)
- ~~**Routine Progression Style**~~ — shipped (per-routine style + mid-block; Training seeds new routines only)
- ~~**Per-profile Deload factors**~~ — shipped (weight/reps on Exercise Profile with auto-push; velocity on routine)

**Solo-lifter queue (updated 2026-10-01):** shipped Swap A↔B, Do groups later, Circuits, Progression Style, Stepper controls. Dropped: Better History Edits, lbs, automatic circuit progression, flaky-network drafts. Next: rack inventory → dropsets on supersets. Then Viewable Progression Data. PT / videos stay parked.

### Code quality & security

- **PHP coverage baseline (advisory)** — Sail: `npm run sail:coverage` → `coverage/index.html`; local baseline **92.28%**; advisory **90%** floor via `test:coverage:threshold` (`continue-on-error`). Hard gates later.
- **Infection mutation baseline** — Sail: `npm run sail:infection` / `composer test:infection` (scope: `Support` + `Policies` + domain `Services` via `infection.json5` → `coverage/infection.html`). **Baseline 2026-09-16:** Covered MSI **79.53%** (1831 mutants; 1227 killed, 337 escaped, 82 timeouts, 178 skipped/slow, 7 ignored). Pilot Support/Policies escapes largely killed via policy/warm-up unit tests. Top escape hotspots: `WorkoutSessionService`, `RoutineBlockExerciseWriter` / `RoutineBlockProfileGuard` (was `RoutineEditorService` → `RoutineBlockWriter` before further D3 splits), `ExerciseProfileService`, `UserDataExporter`, `WorkoutProgressionService`. **Noise policy:** disable `PublicVisibility` / `UnwrapArrayValues` / `ReturnRemoval` / cast mutators / `ArrayItem`; ignore `ArrayItemRemoval`/`MethodCallRemoval` on `with|load|loadMissing|fresh` and `lockForUpdate`. Prefer killing real escapes with asserts. Next: service slices; then diff-only PR Infection; no CI MSI gate yet.
- **Routine editor service seams (D3)** — `RoutineEditorService` sync facade; `RoutineBlockWriter` orchestrates; `RoutineBlockExerciseWriter` + `RoutineBlockProfileGuard` + `RoutineBlockShape` hold the branching (see `docs/refactor/backend-tidy-plan.md`).
- **GDPR (public launch)** — re-grill retention, cookie CMP, and processor DPAs before open registration; beta: privacy page + Account export/delete + invite cascade done

### Ops (internal)

- **Soft host cap ~100 accounts** — prod: Laravel Cloud Flex **512 MiB** app (~17 concurrent HTTP per replica) + MySQL **512 MiB** / **5 GB**. Pause / slow Admin invites before upgrading or asking for money. Not advertised on public FAQ.
- **Maintenance handoff plan** — reduce ongoing Cursor dependence so a human can keep the app running without constant AI spend
- **Storybook for components?** — component catalog to support human handover (from Notion inbox)
- ~~**Business card / flyer design, logo files somewhere**~~ — done

## Backlog: 121 Feedback (gym owner)

Triaged 2026-08-28. Source: Notion [121 Feedback](https://app.notion.com/p/3cae5dd99f0c8077bed9d976fb53af77).

| Raw note                                       | Feature                                                                      |
| ---------------------------------------------- | ---------------------------------------------------------------------------- |
| PT mode; contacts; account switcher            | [PT mode](#grill-pt-mode)                                                    |
| Share workouts                                 | [PT mode](#grill-pt-mode) (PT→client; low demand for 1-2-1 individual share) |
| Videos; custom videos with routines            | [Exercise videos](#grill-exercise-videos-pt)                                 |
| Time range rather than reps; rest 60s circuits | Circuit blocks (shipped)                                                     |

## Grill: PT mode

**Motivation:** Gym-owner 1:1 — PTs need to manage many clients and their programmes, not just their own training.

**Scope sketch (initial):**

- New **User type** (PT) is the likely starting seam.
- PT stores **personal** routines/workouts plus **client** routines (individual and possibly group).
- **Contacts** = client roster (who the PT trains).
- **Share workouts** lives here: primary case is **PT → client** (assign or push a routine). Demand for peer **individual → individual** share looks low for now.
- Client **switching** (was “account switcher?” in raw notes) — PT moves between clients without separate logins per client.

**Open (grill later):**

- PT vs admin vs regular user — roles, invites, billing?
- Client accounts: do clients need their own login, or PT-only records?
- Group clients — one routine shared across a class, or tagged individuals?
- Permissions: can clients edit assigned routines, or view-only / log-only?
- Data ownership and GDPR when PT holds client data
- How sharing is delivered (in-app assign, link, email?)

## Grill: Exercise videos (PT)

**Motivation:** Gym-owner 1:1 — attach demo/form videos to exercises or routines.

**Scope sketch (initial):**

- User-uploadable **video on an exercise** (or routine context TBD).
- Restrict to **PT mode** first — PTs film demos for clients; not a general social upload surface.
- Tied to routines the PT assigns (raw note: “save custom videos to go along with routines”).

**Open (grill later):**

- Storage and delivery: object storage, CDN, transcode, size/duration limits, cost at ~100 users
- Who uploads, who views (PT only vs client sees assigned video)
- Attach to shared catalog exercise vs custom exercise vs routine block
- Privacy, retention, delete on client unlink
- MVP: embed external URL (YouTube/Vimeo) vs hosted upload

## Grill: Routine Progression Style

**Motivation:** Notion inbox “Progression type override per workout” — one Training default is too coarse; programmes differ (e.g. strength vs accessories).

**Decided (2026-09-29):**

- Own **Progression Style** + Progressive Overload **mid-block** (Ask/Auto) on the **Routine**; snapshot onto the Workout at start (same as today, but source is the routine).
- Training keeps those two knobs as **seeds for new routines only** (edits do not rewrite existing routines).
- Domain name stays **Progression Style** (not “progression type”).
- Not a per-start chooser and not an edit of an already-snapshotted workout.

**Shipped:** columns on `routines`, backfill from user Training defaults, editor UI, create/duplicate/sync, WorkoutService reads from routine.
