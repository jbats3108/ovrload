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

Public order matches `/beta-tester-faqs`.

1. **Better History Edits** — warm-up edits; discarded in History (low prio); post-hoc structure edits deferred (prefer Play add first; re-grill later)
2. **Support for lbs** — end-to-end preferred unit (API still kg-centric today)
3. **Gym dumbbell / rack inventory** — full rack range for run-the-rack / planning
4. **Viewable Progression Data** — charts/tables/export; large feature, own grill later
5. **Circuit workouts** — >2 exercises per round; intra-circuit + end-of-circuit rest; rest presets — grill: [Circuit workouts](#grill-circuit-workouts)
6. **Dropsets on supersets** — multi-segment dropsets inside a two-exercise superset round



### Parked (internal — not on public FAQ)

- **Resend → Gmail forward webhook** — optional; forms/mailboxes work without it
- **Strava integration** — OAuth / export / privacy grill later
- **Garmin sync** — after Strava
- **Ad-hoc / off-routine historical log (C2)** — log a lift not on a routine session; own grill (maybe after Play ad-hoc)
- **Flaky-network drafts** — best-effort offline/queue for player logging
- **Benchmark exercises / 1RMs** — track reference lifts / estimated maxes
- **In-app product tour** — after the public `/tutorial` page; own grill
- **PT mode** — new user type; client roster; personal + client routines; PT→client share (includes client switching / former account switcher) — grill: [PT mode](#grill-pt-mode) (parked until after solo-lifter queue)
- **Exercise videos (PT)** — park until PT mode exists — grill: [Exercise videos](#grill-exercise-videos-pt)

**Solo-lifter queue (decided 2026-09-04):** 1) ~~Swap A↔B~~ → 2) ~~Skip block / come back later~~ (shipped as Do groups later) → 3) FAQ: Better History Edits, then lbs, then rack inventory. Circuits / dropsets-on-supersets after that. PT / videos later.

### Code quality & security

- **GDPR (public launch)** — re-grill retention, cookie CMP, and processor DPAs before open registration; beta: privacy page + Account export/delete + invite cascade done



### Ops (internal)

- **Soft host cap ~100 accounts** — prod: Laravel Cloud Flex **512 MiB** app (~17 concurrent HTTP per replica) + MySQL **512 MiB** / **5 GB**. Pause / slow Admin invites before upgrading or asking for money. Not advertised on public FAQ.
- **Maintenance handoff plan** — reduce ongoing Cursor dependence so a human can keep the app running without constant AI spend
- **Storybook for components?** — component catalog to support human handover (from Notion inbox)



## Backlog: 121 Feedback (gym owner)

Triaged 2026-08-28. Source: Notion [121 Feedback](https://app.notion.com/p/3cae5dd99f0c8077bed9d976fb53af77).


| Raw note                                       | Feature                                                                      |
| ---------------------------------------------- | ---------------------------------------------------------------------------- |
| PT mode; contacts; account switcher            | [PT mode](#grill-pt-mode)                                                    |
| Share workouts                                 | [PT mode](#grill-pt-mode) (PT→client; low demand for 1-2-1 individual share) |
| Videos; custom videos with routines            | [Exercise videos](#grill-exercise-videos-pt)                                 |
| Time range rather than reps; rest 60s circuits | [Circuit workouts](#grill-circuit-workouts)                                  |




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

