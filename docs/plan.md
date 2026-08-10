# Plan

Working backlog for OVRLOAD v2. Update this as items ship or get deferred. Domain language stays in `CONTEXT.md`; hard decisions stay in `docs/adr/`.

**Grill cleanup:** when a grilled feature ships, delete its `## Grill: …` section. Move any still-open deferred bullets into **Backlog**; do not keep decided implementation notes here.

**Notion inbox:** after pulling bullets from Notion [Ovrload](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) into this file, clear **only** the list items under `## Backlog:` — leave that header and a single empty bullet (`-`). Do not replace the whole page or delete child pages / other sections.

## Now

-

## Shipped (recent)

Gym-test 2026-07-28 + 2026-07-26 + remaining product history. Newest first within each batch where noted.

1. **Custom user exercises** — private per-user customs from routine ExercisePicker; shared catalog stays admin/import-only
2. **Choose an alternate exercise for Deload sessions** — optional Deload Alternate (exercise + own weight) per block exercise; Deload snapshot only; Singles when alternate set
3. **Laravel Sail local stack** — Dockerised local app (PHP/MySQL/Redis/Mailpit/queue/Vite); `npm run sail:up` LAN phone access; host `composer run dev` fallback (#63)
4. **Add Historical Workouts** — History → Add historical; when-first (no “now” default); deload; +/− set / mark group skipped; warm-ups %×Set 1 (hint + override); progression only if latest non-deload; OK while Play in progress; UI “group” not “block” on skip actions
5. **Skip rest of block** — − Set trims incomplete rounds (never last); confirmed Skip rest of group deletes remaining incompletes (warm-ups + working → 0); Set + Rest; no shame rows in History (#64)
6. **Since-last-deload counts** — per-routine dashboard standards since latest finished deload; soft Deload hint at ≥ Deload Velocity (`deload_every_n`, editor + Training defaults; 0 = never); #47
7. **Domain mail + beta forms + PWA icons** — Resend domain mailboxes; invite `MAIL_REPLY_TO_ADDRESS`; first-party `/invite-request` & `/feedback` (stored + notify invite@/feedback@); privacy drops Tally; Ko-Fi link; padded/maskable PWA icons (#56)
8. **Exercise catalog curation** — curated 174 shared lifts (original short list + selective free-exercise-db); `exercises:import` soft-deletes extras unless `--no-prune`
9. **Nicer confirms** — ~~replace browser `confirm`/`alert` with in-app dialogs~~ done (`confirmDialog` + `ConfirmDialogHost`; RestStage inline skip unchanged)
10. **"Block" naming** — ~~UI/copy only: Play/History drop “Block N” (show `Superset` when needed); Up next drops Block N; setup hints use exercise names; editor/settings leftover noun = Exercise; domain `Block` unchanged~~ done
11. **Set x/y in exercise header (Play)** — ~~set progress in the big exercise header (and log sheet), labeled Warm-up / Working~~ done
12. **Bump when mode** — ~~Settings “Bump when”: Any set / Last set at top weight; snapshotted on workout start; Floor kept for carry-forward; Bump = prescribed Target (no separate Progression Target / editor Bump); log sheet `Floor X. Bump @ Y`; confirm on finish + history re-eval~~ done
13. **PWA app shell** — ~~tabbed app shell; haptics~~ done (mobile bottom tabs: Dashboard · History · Training · Settings; desktop sidebar unchanged; player haptics on Done / Log set; rest-end vibrate already shipped)
14. **Duplicate routine** — ~~clone an existing routine as a starting point~~ done (POST duplicate; deep-copies blocks / set groups / warm-ups / dropsets / deload; opens editor as `{name} (copy)`)
15. **Superset setup preview** — ~~show both exercises during setup~~ done (Setup Up Next lists A + B for the upcoming round)
16. **History: group block sets** — ~~group sets by block in history UI~~ done (one section per block; working sets grouped by exercise)
17. **PWA installable (phase 1)** — ~~manifest, Apple meta tags, service worker at `/sw.js` (root scope), iOS install banner~~ done (#23)
18. **Progression defaults UI** — ~~Settings Achievement Floor / Progression Target + editor Floor / Bump overrides~~ done (empty override inherits user default; placeholders from Settings)
19. **Slugs / ULIDs in routes** — ~~investigate slugs instead of IDs~~ done: [ADR-0006](adr/0006-slugs-and-ulids-in-routes.md) (routine slugs + workout ULIDs)
20. **Rest skip confirm** — ~~inline Skip rest confirm in player~~ done (`RestStage.vue`)
21. **Type-ahead exercise picker** — ~~separate find bar + native `<select>` (no search in dropdown)~~ done (one control: tap exercise → bottom sheet with focused search + filtered list; mobile + desktop)
22. **Setup between warm-ups** — ~~per-step setup after warm-up steps; setup then warm-up rest; block Setup→work unchanged~~ done
23. **Add/remove set player bugs** — ~~− Set advanced focus / left `set N of 1`; last-round − Set skipped the block~~ done (reindex after remove; keep focus on add; hide − Set on last working round)
24. **Player layout tweaks** — ~~centralise text, clearer section separation, bigger elements; stronger set-of-x highlight~~ done
25. **Set x/n on setup** — ~~show set progress (x of n) during setup~~ done (Up next on setup/rest: `Set x/n` from planned group count)
26. **Complete screen full-page** — ~~log-set complete UI should cover the whole page~~ done (full-screen log sheet; keyboard overlays content so Log/Cancel stay put; finish Complete stage hides player header)
27. **Countdown beeps** — ~~rest/timer countdown audio cues~~ done (ticks at 5…1 + long end tone; vibrate mirrors when available)
28. **Admin nav order** — ~~push Admin to the bottom of the top-nav items~~ done (after Training in primary rail/drawer)
29. **Remove clickable titles** — ~~titles should not navigate; use explicit buttons only~~ done (dashboard routine name is plain text; edit via icon)
30. **Bigger edit/delete icons** — ~~increase affordance size for edit/delete controls in the UI~~ done (dashboard routine cards: `size-5` + larger hit target)
31. **Soft-fail not-found / errors** — ~~no raw error pages for expected GET misses/forbids; redirect + flash/toast~~ done (authenticated web; guests/admin/mutations stay hard)
32. **Finished workout history** — browse/edit finished workouts at `/history`; dashboard recent strip + nav; warm-ups read-only; working weight + reps editable; re-eval progression on latest non-deload finish (carry-forward, bumps, undo via Bump Records; ADR-0004)
33. **Complete-then-log UX** — ~~Done on main stage opens full-page log sheet; Log set commits; Cancel aborts without server write; main stage is display-only with plate guide~~ done
34. **Rest-end alert + leave-during-rest** — sound/vibration when rest hits zero in foreground; notification permission on first rest + background notification when tab is hidden; clock-based rest sync on visibility return
35. **Prev set weight → next** — ~~pending-rest blocks focus race; client `lastWorkingWeightKg` + prior logged weight~~ done
36. **Keep screen awake in Play** — ~~Screen Wake Lock while player mounted; re-request on visibility~~ done
37. **Preview next during rest / setup** — ~~Up next card: exercise, set, weight/reps, plate stack when barbell~~ done
38. **Plate guide visibility in Play** — ~~works for barbell/EZ; missing equipment on pre-import orphans~~ done (audit + merge original short-name catalog)
39. **Progression on finish** — ~~carry-forward highest achieved top weight; confirm bumps when progression target hit; skip both for deload workouts~~ done
40. **Mid-session structure edits** — ~~mutate the in-progress workout snapshot (not the routine) from the player~~ done (add/remove incomplete working sets; reindex + last-round − Set guard)
41. **More app-like mobile behaviour** — ~~chrome polish: safe areas, player full-bleed (no AppLayout), leave confirm, overscroll off on player+editor~~ done (PWA install #5; bottom nav in #1)
42. **User default warm-up %s and reps** — ~~prefs on the user; per-step %×reps on warm-up steps; seed into new blocks; Settings → Training~~ done
43. **Restyle whole app to match Overload branding** — ~~zinc + lime~~ done: dark-first near-black + neon yellow primary + cyan accent (`docs/branding.md`, `resources/css/app.css`)
44. **Find and import exercises** — ~~shared catalog JSON + `exercises:import` + seeder; editor find filter; index scoped to `forUser`~~ done (~80 lifts)
45. **Admin panel** — ~~thin Inertia admin: exercises, muscle groups, read-only users; sidebar link for admins~~ done
46. **Dead code audit (v1 leftovers)** — ~~JSON catalog APIs, unused MG update, starter UI packages, unused permission seeders~~ done
47. **Strip Laravel starter-kit UI** — ~~remove obvious Breeze/starter chrome and behaviours that still read as the stock kit~~ done (branded OVRLOAD home; dead search/footer/auth variants removed)
48. **Rebrand to OVRLOAD** — ~~rename product surfaces to **OVRLOAD**; mark/icon and related chrome around **OVR** / \*\*OVRLD~~\*\* done (`docs/branding.md`; logos, home, auth, `APP_NAME`)
49. **Plate calculator UI** — ~~Settings for bars/plates; player shows nearest loadable stack~~ done

- Equipment classification: catalog `equipment` on exercises; snapshot into workouts; plate guide only for barbell / E-Z curl bar

50. **Player / editor UX polish** — ~~finish/abandon in-progress; edit affordance; exercise find results; mobile editor scroll, compact warm-up, in-card search, add-block placement~~ done
51. **Warm-up weight prefill in Play** — ~~incomplete warm-up sets should fill the weight field from `% × working` (`target_weight_kg`), not the previous logged warm-up; fix Target label `v-else` on reps~~ done
52. **Warm-up setup steps** — ~~plan setup (press-when-done) pauses inside the warm-up flow, not only setup-after-block~~ done (`has_setup_after_warm_up`: once between last warm-up and first working)
53. **Rest after warm-ups** — ~~make warm-up group rest first-class in editor + Play (rest after warm-up sets / before working)~~ done (editor exposes WU rest; Play already used group rest)
54. **Clear block warm-up** — ~~one-tap remove all warm-up steps from a block in the editor~~ done
55. **Warm-up defaults scope** — ~~Settings: seed warm-ups into every new block vs first block only~~ done
56. **Dropsets** — ~~per working-set-slot multi-segment sets in editor + Play; update `CONTEXT.md` when shipping~~ done
57. **Login screen on every open** — ~~authenticated users hitting `/` saw the guest home/login UI; bfcache could restore stale guest pages after sign-in~~ done (home redirect + guest-page bfcache reload)

## Backlog

Single triage list — reprioritize across buckets as needed. **Features (FAQ)** are listed on the public help/FAQ page for beta testers. **Polish & mobile integration** is shipped-flow UX, not net-new capability. Notion [inbox](https://app.notion.com/p/3aae5dd99f0c80ad928ade1a5c6b0749) → pull new bullets into the right bucket below.

-

### Features (FAQ)

Public order matches `/beta-tester-faqs`.

1. **Tutorial** — walkthrough for settings, routines, create/manage
2. ~~**Add Historical Workouts**~~ — recently added (History → Add historical)
3. ~~**Custom user exercises**~~ — recently added (private customs from routine ExercisePicker; shared catalog stays admin/import-only)
4. **Better History Edits** — warm-up edits; discarded in History; add/remove exercises and sets on a logged workout
5. **Support for lbs** — end-to-end preferred unit (API still kg-centric today)
6. ~~**Choose an alternate exercise for Deload sessions**~~ — recently added (optional Deload Alternate + own weight per block exercise; Deload snapshot only; Singles when alternate set)
7. **Gym dumbbell / rack inventory** — full rack range for run-the-rack / planning
8. **Viewable Progression Data** — charts/tables/export; large feature, own grill later

### Parked (internal — not on public FAQ)

- **Resend → Gmail forward webhook** — optional; forms/mailboxes work without it
- **Strava integration** — OAuth / export / privacy grill later
- **Garmin sync** — after Strava
- **Demote dropset → single in Play**
- **Ad-hoc setup from player** — beyond planned `has_setup_after`
- **Transition duration preference** — stored pref for A→B pause in supersets (today client-side)
- **Dropsets on supersets**
- **Flaky-network drafts** — best-effort offline/queue for player logging
- **Benchmark exercises / 1RMs** — track reference lifts / estimated maxes
- **Bump between sets** — auto / config / ask whether to bump mid-block (not only on finish)

### Polish & mobile integration

- ~~**Plate suggestions from prior sets**~~ — heaviest-first default; Edit plates toggle; logged stack resume-safe continuity for next same-exercise working set
- ~~**Skip rest of block**~~ (was “Bail on last set”) — − Set trims incomplete rounds (never last); confirmed Skip rest of block clears remaining incompletes; Set + Rest; #64

### Bugfixes

- ~~**Save error feedback**~~ — show validation errors near mobile Save + scroll into view on failure
- ~~**Empty rest as 0**~~ — treat cleared rest inputs as 0 on routine save

### Code quality & security

- **GDPR (public launch)** — re-grill retention, cookie CMP, and processor DPAs before open registration; beta: privacy page + Settings export/delete + invite cascade done

### Ops (internal)

- **Soft host cap ~100 accounts** — prod: Laravel Cloud Flex **512 MiB** app (~17 concurrent HTTP per replica) + MySQL **512 MiB** / **5 GB**. Pause / slow Admin invites before upgrading or asking for money. Not advertised on public FAQ.
- **Maintenance handoff plan** — reduce ongoing Cursor dependence so a human can keep the app running without constant AI spend
- ~~**Dockerise?**~~ — shipped as Laravel Sail local stack (#63); prod remains Laravel Cloud
