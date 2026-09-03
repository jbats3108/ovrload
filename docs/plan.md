# Plan

Working backlog for OVRLOAD v2. Update this as items ship or get deferred. Domain language stays in `CONTEXT.md`; hard decisions stay in `docs/adr/`.

**Grill cleanup:** when a grilled feature ships, delete its `## Grill: …` section. Move any still-open deferred bullets into **Backlog**; do not keep decided implementation notes here.

**Notion inbox:** after pulling bullets from Notion [Ovrload](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) into this file, clear **only** the list items under `## Backlog:` — leave that header and a single empty bullet (`-`). Do not replace the whole page or delete child pages / other sections.

## Now

-

## Shipped (recent)

**Backend tidy (refactor/backend-tidy, 2026-09-01)** — slices A–F: profile reference tracking + route auth; DRY assignment/picker/structure helpers; WorkoutSetLogger; WorkoutSession/Snapshot + ExerciseProfilePreset/Assignment service splits; RoutineBlockStructureData; scoped workout bind, sync filter, seed backfill removal.

Gym-test 2026-07-28 + 2026-07-26 + remaining product history. Newest first within each batch where noted.

1. **Dead backend code cull** — unused ExerciseProfile relations/enum helpers; unreachable NotEditable catch; editor options ignore shared-only IDs; `referenceCount` collapsed to `assigned_routines`; sync skips soft-deleted routines; empty payload stub; unused policy methods; test-only plate/set/exercise helpers. Unused Eloquent inverses of live FKs left in place.
1. **Play add-exercise less prominent** — control moved into the player header vs primary session actions (#89)
1. **Exercise profiles** — user defaults, OVRLOAD presets, custom recipes, routine/block assignment, explicit sync, and admin publication flow. Preferences “used in” is picker-visible routines only (#88 / #90 / #94)
2. **Welcome email** — queued mail on register; tutorial + Beta FAQs links; reply-to Jamie / admin mailbox
2. **Default Target reps in Training** — Preferences field; seeds new editor blocks and Play ad-hoc (fallback 6)
3. **Demote dropset → single in Play** — incomplete dropset → clear segments back to a single (mirror of Promote)
4. **Add exercise in Play** — snapshot-only ad-hoc exercise from catalog/custom picker; three working sets; current focus stays in place; no progression
5. **Fewer dashboard history items** — dashboard recent strip shows 3, not 5
6. **PWA install instructions** — FAQ “How do I install…?” + tutorial `#install` (iOS Safari Share → Add to Home Screen; Android Chrome Install / Add to Home screen)
7. **Tutorial** — public `/tutorial` walkthrough (page first; in-app tour later); Preferences / Account / empty Dashboard / History entry points; device-matched screenshots
8. **Custom user exercises** — private per-user customs from routine ExercisePicker; shared catalog stays admin/import-only
9. **Choose an alternate exercise for Deload sessions** — optional Deload Alternate (exercise + own weight) per block exercise; Deload snapshot only; Singles when alternate set
10. **Laravel Sail local stack** — Dockerised local app (PHP/MySQL/Redis/Mailpit/queue/Vite); `npm run sail:up` LAN phone access; host `composer run dev` fallback (#63)
11. **Add Historical Workouts** — History → Add historical; when-first (no “now” default); deload; +/− set / mark group skipped; warm-ups %×Set 1 (hint + override); progression only if latest non-deload; OK while Play in progress; UI “group” not “block” on skip actions
12. **Skip rest of block** — − Set trims incomplete rounds (never last); confirmed Skip rest of group deletes remaining incompletes (warm-ups + working → 0); Set + Rest; no shame rows in History (#64)
13. **Since-last-deload counts** — per-routine dashboard standards since latest finished deload; soft Deload hint at ≥ Deload Velocity (`deload_every_n`, editor + Training defaults; 0 = never); #47
14. **Domain mail + beta forms + PWA icons** — Resend domain mailboxes; invite `MAIL_REPLY_TO_ADDRESS`; first-party `/invite-request` & `/feedback` (stored + notify invite@/feedback@); privacy drops Tally; Ko-Fi link; padded/maskable PWA icons (#56)
15. **Exercise catalog curation** — curated 174 shared lifts (original short list + selective free-exercise-db); seeder/`ExerciseCatalogImporter` soft-deletes extras on import
16. **Nicer confirms** — ~~replace browser `confirm`/`alert` with in-app dialogs~~ done (`confirmDialog` + `ConfirmDialogHost`; RestStage inline skip unchanged)
13. **"Block" naming** — ~~UI/copy only: Play/History drop “Block N” (show `Superset` when needed); Up next drops Block N; setup hints use exercise names; editor/settings leftover noun = Exercise; domain `Block` unchanged~~ done
14. **Set x/y in exercise header (Play)** — ~~set progress in the big exercise header (and log sheet), labeled Warm-up / Working~~ done
15. **Progression style + mid-block bumps** — ~~Preferences “Progression style”: Straight Sets / Progressive Overload; Progressive mid-block Ask or Auto; snapshotted on workout start; per-set next-set +2.5 kg ramp; finish bump rules bundled per style; Tutorial updated~~ done
16. **Bump when mode** — ~~superseded by Progression style (Straight Sets ≈ any set at finish; Progressive ≈ last at top at finish + mid-block)~~ done
16. **PWA app shell** — ~~tabbed app shell; haptics~~ done (mobile bottom tabs: Dashboard · History · Preferences · Account, plus Admin for admins; desktop sidebar unchanged; player haptics on Done / Log set; rest-end vibrate already shipped)
17. **Duplicate routine** — ~~clone an existing routine as a starting point~~ done (POST duplicate; deep-copies blocks / set groups / warm-ups / dropsets / deload; opens editor as `{name} (copy)`)
18. **Superset setup preview** — ~~show both exercises during setup~~ done (Setup Up Next lists A + B for the upcoming round)
19. **History: group block sets** — ~~group sets by block in history UI~~ done (one section per block; working sets grouped by exercise)
20. **PWA installable (phase 1)** — ~~manifest, Apple meta tags, service worker at `/sw.js` (root scope), iOS install banner~~ done (#23)
21. **Progression defaults UI** — ~~Preferences Achievement Floor / Progression Target + editor Floor / Bump overrides~~ done (empty override inherits user default; placeholders from Preferences)
22. **Slugs / ULIDs in routes** — ~~investigate slugs instead of IDs~~ done: [ADR-0006](adr/0006-slugs-and-ulids-in-routes.md) (routine slugs + workout ULIDs)
23. **Rest skip confirm** — ~~inline Skip rest confirm in player~~ done (`RestStage.vue`)
24. **Type-ahead exercise picker** — ~~separate find bar + native `<select>` (no search in dropdown)~~ done (one control: tap exercise → bottom sheet with focused search + filtered list; mobile + desktop)
25. **Setup between warm-ups** — ~~per-step setup after warm-up steps; setup then warm-up rest; block Setup→work unchanged~~ done
26. **Add/remove set player bugs** — ~~− Set advanced focus / left `set N of 1`; last-round − Set skipped the block~~ done (reindex after remove; keep focus on add; hide − Set on last working round)
27. **Player layout tweaks** — ~~centralise text, clearer section separation, bigger elements; stronger set-of-x highlight~~ done
28. **Set x/n on setup** — ~~show set progress (x of n) during setup~~ done (Up next on setup/rest: `Set x/n` from planned group count)
29. **Complete screen full-page** — ~~log-set complete UI should cover the whole page~~ done (full-screen log sheet; keyboard overlays content so Log/Cancel stay put; finish Complete stage hides player header)
30. **Countdown beeps** — ~~rest/timer countdown audio cues~~ done (ticks at 5…1 + long end tone; vibrate mirrors when available)
31. **Admin nav order** — ~~push Admin to the bottom of the top-nav items~~ done (after Account in the mobile tray; remains in the primary rail/drawer)
32. **Remove clickable titles** — ~~titles should not navigate; use explicit buttons only~~ done (dashboard routine name is plain text; edit via icon)
33. **Bigger edit/delete icons** — ~~increase affordance size for edit/delete controls in the UI~~ done (dashboard routine cards: `size-5` + larger hit target)
34. **Soft-fail not-found / errors** — ~~no raw error pages for expected GET misses/forbids; redirect + flash/toast~~ done (authenticated web; guests/admin/mutations stay hard)
35. **Finished workout history** — browse/edit finished workouts at `/history`; dashboard recent strip + nav; warm-ups read-only; working weight + reps editable; re-eval progression on latest non-deload finish (carry-forward, bumps, undo via Bump Records; ADR-0004)
36. **Complete-then-log UX** — ~~Done on main stage opens full-page log sheet; Log set commits; Cancel aborts without server write; main stage is display-only with plate guide~~ done
37. **Rest-end alert + leave-during-rest** — sound/vibration when rest hits zero in foreground; notification permission on first rest + background notification when tab is hidden; clock-based rest sync on visibility return
38. **Prev set weight → next** — ~~pending-rest blocks focus race; client `lastWorkingWeightKg` + prior logged weight~~ done
39. **Keep screen awake in Play** — ~~Screen Wake Lock while player mounted; re-request on visibility~~ done
40. **Preview next during rest / setup** — ~~Up next card: exercise, set, weight/reps, plate stack when barbell~~ done
41. **Plate guide visibility in Play** — ~~works for barbell/EZ; missing equipment on pre-import orphans~~ done (audit + merge original short-name catalog)
42. **Progression on finish** — ~~carry-forward highest achieved top weight; confirm bumps when progression target hit; skip both for deload workouts~~ done
43. **Mid-session structure edits** — ~~mutate the in-progress workout snapshot (not the routine) from the player~~ done (add/remove incomplete working sets; reindex + last-round − Set guard)
44. **More app-like mobile behaviour** — ~~chrome polish: safe areas, player full-bleed (no AppLayout), leave confirm, overscroll off on player+editor~~ done (PWA install #5; bottom nav in #1)
45. **User default warm-up %s and reps** — ~~prefs on the user; per-step %×reps on warm-up steps; seed into new blocks; Preferences~~ done
46. **Restyle whole app to match Overload branding** — ~~zinc + lime~~ done: dark-first near-black + neon yellow primary + cyan accent (`docs/branding.md`, `resources/css/app.css`)
47. **Find and import exercises** — ~~shared catalog JSON + seeder/`ExerciseCatalogImporter`; editor find filter; index scoped to `forUser`~~ done (~80 lifts)
48. **Admin panel** — ~~thin Inertia admin: exercises, muscle groups, read-only users; sidebar link for admins~~ done
49. **Dead code audit (v1 leftovers)** — ~~JSON catalog APIs, unused MG update, starter UI packages, unused permission seeders~~ done
50. **Strip Laravel starter-kit UI** — ~~remove obvious Breeze/starter chrome and behaviours that still read as the stock kit~~ done (branded OVRLOAD home; dead search/footer/auth variants removed)
51. **Rebrand to OVRLOAD** — ~~rename product surfaces to **OVRLOAD**; mark/icon and related chrome around **OVR** / \*\*OVRLD~~\*\* done (`docs/branding.md`; logos, home, auth, `APP_NAME`)
52. **Plate calculator UI** — ~~Preferences for bars/plates; player shows nearest loadable stack~~ done

- Equipment classification: catalog `equipment` on exercises; snapshot into workouts; plate guide only for barbell / E-Z curl bar

52. **Player / editor UX polish** — ~~finish/abandon in-progress; edit affordance; exercise find results; mobile editor scroll, compact warm-up, in-card search, add-block placement~~ done
53. **Warm-up weight prefill in Play** — ~~incomplete warm-up sets should fill the weight field from `% × working` (`target_weight_kg`), not the previous logged warm-up; fix Target label `v-else` on reps~~ done
54. **Warm-up setup steps** — ~~plan setup (press-when-done) pauses inside the warm-up flow, not only setup-after-block~~ done (`has_setup_after_warm_up`: once between last warm-up and first working)
55. **Rest after warm-ups** — ~~make warm-up group rest first-class in editor + Play (rest after warm-up sets / before working)~~ done (editor exposes WU rest; Play already used group rest)
56. **Clear block warm-up** — ~~one-tap remove all warm-up steps from a block in the editor~~ done
57. **Warm-up defaults scope** — ~~Preferences: seed warm-ups into every new block vs first block only~~ done
58. **Dropsets** — ~~per working-set-slot multi-segment sets in editor + Play; update `CONTEXT.md` when shipping~~ done
59. **Login screen on every open** — ~~authenticated users hitting `/` saw the guest home/login UI; bfcache could restore stale guest pages after sign-in~~ done (home redirect + guest-page bfcache reload)

## Backlog

Single triage list — reprioritize across buckets as needed. **Features (FAQ)** are listed on the public help/FAQ page for beta testers. **Polish & mobile integration** is shipped-flow UX, not net-new capability. Notion [inbox](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) → pull new bullets into the right bucket below.

-

### Features (FAQ)

Public order matches `/beta-tester-faqs`.

1. ~~**Add Historical Workouts**~~ — recently added (History → Add historical)
2. ~~**Custom user exercises**~~ — recently added (private customs from routine ExercisePicker; shared catalog stays admin/import-only)
3. ~~**Add exercise in Play**~~ — recently added (append a lean ad-hoc block mid-session from catalog/custom; snapshot-only; current focus stays in place)
4. **Better History Edits** — warm-up edits; discarded in History (low prio); post-hoc structure edits deferred (prefer Play add first; re-grill later)
5. **Support for lbs** — end-to-end preferred unit (API still kg-centric today)
6. ~~**Choose an alternate exercise for Deload sessions**~~ — recently added (optional Deload Alternate + own weight per block exercise; Deload snapshot only; Singles when alternate set)
7. **Gym dumbbell / rack inventory** — full rack range for run-the-rack / planning
8. **Viewable Progression Data** — charts/tables/export; large feature, own grill later

### Parked (internal — not on public FAQ)

- **Resend → Gmail forward webhook** — optional; forms/mailboxes work without it
- **Strava integration** — OAuth / export / privacy grill later
- **Garmin sync** — after Strava
- **Skip block / come back later in Play** — leave a block and return after doing a later one (machine busy)
- **Ad-hoc / off-routine historical log (C2)** — log a lift not on a routine session; own grill (maybe after Play ad-hoc)
- **Dropsets on supersets**
- **Flaky-network drafts** — best-effort offline/queue for player logging
- **Benchmark exercises / 1RMs** — track reference lifts / estimated maxes
- **In-app product tour** — after the public `/tutorial` page; own grill
- ~~**Account switcher**~~ — folded into **PT mode** grill (client switching)
- **PT mode** — new user type; client roster; personal + client routines; PT→client share — grill: [PT mode](#grill-pt-mode)
- **Exercise videos (PT)** — user-uploadable video per exercise/routine; PT-mode scope first — grill: [Exercise videos](#grill-exercise-videos-pt)
- **Circuit workouts** — >2 exercises per round; intra-circuit + end-of-circuit rest; rest presets — grill: [Circuit workouts](#grill-circuit-workouts)

### Polish & mobile integration

- **Editor vs Preferences density** — mobile: first Routine sheet (name/profile/Deload); Target/Floor/Rest/WU/deload-alt collapsed until Custom. Remaining: desktop acronym/disclosure pass; swap superset A↔B

### Bugfixes

-

### Code quality & security

- **PHPStan warmup-step / profile typing** — ~~advisory CI annotations (`quality` job `continue-on-error`): `normalizeList()` wants `list` not `array` (`User`, `ExerciseProfile`, …); `toStorage()` / profile Data constructors still typed without `mode`; `WeightKgSegmentData::gramsList()` missing `DataCollection` `TKey,TValue`~~ done (#99)
- **Node 20 on git-auto-commit-action** — ~~`stefanzweifel/git-auto-commit-action@v6` still targets Node 20; GitHub runners force Node 24~~ done (`@v7`, Node 24)
- **Frontend decomposition & abstraction review** — review large Vue modules for sensible seams, reduce repeated code where locality improves, and split only when the abstraction earns its interface. **Done (refactor/backend-tidy):** `exerciseProfileAssignment.ts` + `exerciseProfileApply.ts`; `playerSetLog.ts` + `buildCompleteSetPayload()`; `playerSessionMutations.ts` (session route visits mirroring `WorkoutSessionService`); `useWorkoutPlayer` keeps orchestration. ~~**PHP tidy leftover:** inline `assignedRoutinesForReferencedIds` callable in `ExerciseProfileAssignmentService` (Slice A dual-path artifact; one strategy remains).~~ done
- **Find N+1 queries (Sentry)** — ~~triage / fix N+1s flagged in Sentry~~ done (OVRLOAD-3: bulk-delete workout set segments on historical create)
- **PHP 8.5 typed class constants** — ~~`rector.php` `withPhpSets()` includes PHP 8.3 `AddTypeToConstRector`, but ~31 untyped public consts remain on non-final classes (`WorkoutService`, `WorkoutHistoryService`, …); Rector skips them (subclass override risk). Mark services `final` / consts `final const`, or hand-type `string`/`int`/`array`~~ done
- **PHP 8.5 pipe operator (`|>`)** — ~~Rector has `NestedFuncCallsToPipeOperatorRector` + `SequentialAssignmentsToPipeOperatorRector` but they are **not** in the default `php85` set (style, not migration). Opt in via `rector.php`, dry-run, then apply where readability wins~~ done
- **Remove dead / one-time code** — ~~retire `exercises:import` + `exercises:audit`; drop legacy `ExerciseProfileBackfillService`; keep JSON + seeders~~ done
- **Exercise preset defs in code** — ~~moved OVRLOAD presets to `database/data/exercise-profile-presets.json`; seeder + migrate path read JSON~~ done
- **GDPR (public launch)** — re-grill retention, cookie CMP, and processor DPAs before open registration; beta: privacy page + Account export/delete + invite cascade done

### Ops (internal)

- **Soft host cap ~100 accounts** — prod: Laravel Cloud Flex **512 MiB** app (~17 concurrent HTTP per replica) + MySQL **512 MiB** / **5 GB**. Pause / slow Admin invites before upgrading or asking for money. Not advertised on public FAQ.
- **Maintenance handoff plan** — reduce ongoing Cursor dependence so a human can keep the app running without constant AI spend

## Backlog: 121 Feedback (gym owner)

Triaged 2026-08-28. Source: Notion [121 Feedback](https://app.notion.com/p/3cae5dd99f0c8077bed9d976fb53af77).

| Raw note | Feature |
| --- | --- |
| PT mode; contacts; account switcher | [PT mode](#grill-pt-mode) |
| Share workouts | [PT mode](#grill-pt-mode) (PT→client; low demand for 1-2-1 individual share) |
| Videos; custom videos with routines | [Exercise videos](#grill-exercise-videos-pt) |
| Time range rather than reps; rest 60s circuits | [Circuit workouts](#grill-circuit-workouts) |

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

## Grill: Circuit workouts

**Motivation:** Gym-owner 1:1 — circuit-style training beyond two-exercise supersets.

**Scope sketch (initial):**

- New structure (like **Superset**, but **>2 exercises** per round).
- **Rest between stages** within a circuit round; **longer rest at end** of the full circuit (cf. superset: transition A→B, then group rest).
- **Rest presets** for circuit modes (raw note: “rest 60s circuits”) — same idea as presets elsewhere.
- **Time range rather than reps** for circuit-style work (duration-based sets) — may belong here or as a circuit set type; grill together.

**Open (grill later):**

- Domain name and model: new block kind vs generalised “round group”
- Max exercises per circuit; order within a round
- Warm-ups and setup in circuits
- Progression rules (if any) for timed vs rep-based circuit sets
- Player UX: how “stage” rest differs visually from end-of-circuit rest
- Relationship to existing Superset machinery — extend or parallel type
