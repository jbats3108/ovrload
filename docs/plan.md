# Plan

Working backlog for OVRLOAD v2. Update this as items ship or get deferred. Domain language stays in `CONTEXT.md`; hard decisions stay in `docs/adr/`.

**Grill cleanup:** when a grilled feature ships, delete its `## Grill: …` section. Move any still-open deferred bullets into **Backlog**; do not keep decided implementation notes here.

**Notion inbox:** after pulling bullets from Notion [Ovrload](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) into this file, clear **only** the list items under `## Backlog:` — leave that header and a single empty bullet (`-`). Do not replace the whole page or delete child pages / other sections.

## Now

-

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
7. **Gym dumbbell / rack inventory** — full rack range for run-the-rack / planning — deprioritized (low demand)
8. **Viewable Progression Data** — charts/tables/export; large feature, own grill later — deprioritized (low demand)
9. ~~**Circuit workouts**~~ — recently added
10. ~~**Skip a block and come back later in Play**~~ — shipped as **Do groups later**; kept on public FAQ as recently added
11. **Dropsets on supersets** — multi-segment dropsets inside a two-exercise superset round — deprioritized (low demand)
12. ~~**Routine Progression Style**~~ — recently added

### Parked (internal — not on public FAQ)

- ~~**Stepper controls for reps and sets**~~ — shipped (clearable +/− on sets/reps/floor; validate on save)
- ~~**Play quick-confirm (Target hit / Floor hit)**~~ — shipped (working: Target hit / Floor hit; warm-up: Done=target; Edit sheet; dropset/timed keep Done→sheet)
- ~~**Flexible block timing and entries**~~ — shipped (timed Singles + circuits ≥1 station; no new block type)
- **Strava integration** — OAuth / export / privacy grill later
- **Garmin sync** — after Strava
- **Ad-hoc / off-routine historical log (C2)** — log a lift not on a routine session; own grill (maybe after Play ad-hoc)
- **Benchmark exercises / 1RMs** — track reference lifts / estimated maxes
- **In-app product tour** — after the public `/tutorial` page; own grill
- **PT mode** — new user type; client roster; personal + client routines; PT→client share (includes client switching / former account switcher) — grill: [PT mode](#grill-pt-mode) (solo-lifter queue done; unpark when ready)
- **Exercise videos (PT)** — park until PT mode exists — grill: [Exercise videos](#grill-exercise-videos-pt)
- ~~**Routine Progression Style**~~ — shipped (per-routine style + mid-block; Training seeds new routines only)
- ~~**Per-profile Deload factors**~~ — shipped (weight/reps on Exercise Profile with auto-push; velocity on routine)

**Solo-lifter queue (updated 2026-10-28):** shipped Swap A↔B, Do groups later, Circuits, Progression Style, Stepper controls, timed Singles + 1-station circuits. Dropped: Better History Edits, lbs, automatic circuit progression, flaky-network drafts. Deprioritized (low demand): rack inventory, dropsets on supersets, Viewable Progression Data. Public FAQ feature list is clear of high-demand solo items.

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

**Decided (2026-10-28, high level):**

- **Stance:** product extends to trainers; solo UX unchanged; lifters stay the core.
- **Target:** one pilot gym, invite-only. No billing.
- **PT** = flag on **User**, set by an admin toggle in Admin. No self-serve PT signup. PTs can still train personally.
- **Client** has two kinds:
  - **Managed** — a User row with no login, owned by exactly one PT. All routine/workout/progression logic reused.
  - **Linked** — a normal User who accepts a PT link (consent) and can unlink any time; history stays with the client.
- **Claim path:** a managed client can later gain a login and keep history (designed now, built later).
- **Acting as a client:** PT writes only to managed clients, via a context switch (stays signed in as self; persistent "Acting as <client>" banner; one-tap return). For linked clients the PT gets read-only history and can assign routine copies; never logs or edits as them.
- **Assigned routines:** one-way copy into the client's account; client owns and edits it; no sync back. Updates = assign a new copy.
- **Roster:** one PT per client. Managed clients count toward the ~100-account soft cap. Config limit **25 per PT** (no UI).
- **GDPR (beta):** PT confirms client consent when creating a managed client; PT can export/delete any managed client; deleting a PT prompts export first, then deletes their managed clients. Full retention/processor re-grill stays on the public-launch item.
- **Naming:** **PT** and **Client** in `CONTEXT.md` (UI copy: "Trainer" / "Client"). Avoid Athlete, Member, Customer.
- **Out of v1:** PT notes, messaging, scheduling, billing, videos, group clients (group = N individual assignments).
- **Public docs:** nothing on FAQ/tutorial until slice 1 ships; then document per the tutorial rule.

**Open:**

- **Slice order / implementation sequencing** — deferred. Proposed: (1) PT flag, roster, managed clients, switching; (2) link login clients with consent; (3) assign copies + read-only linked history.
- Unpark from Parked once slice order is agreed.

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
