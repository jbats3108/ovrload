# OVRLOAD

Personal strength training for serious lifters: plan routines, log sessions, track progressive overload. Lifters are the core; trainers (PTs) get light tooling to manage and assign to their clients — no messaging, scheduling, or billing. Product branding (name, mark, visual direction) lives in `docs/branding.md`.

## Language

**User**:
The account holder who owns routines, workouts, customs, and a plate profile.
_Avoid_: Athlete, lifter, member

**PT**:
A User with the trainer flag, granted by an admin. Can still train personally. Manages a roster of Clients. UI copy: "Trainer".
_Avoid_: Coach, admin

**Client**:
A User a PT works with. **Managed**: no login, owned by one PT, who acts as them via a context switch. **Linked**: a normal User who accepted the PT's link; the PT sees read-only history and can assign Routine copies. One PT per Client. UI copy: "Client".
_Avoid_: Athlete, member, customer

**Routine**:
A named plan the user owns and can start as a workout. Ordered list of blocks. Flat — no calendar or “today’s workout” scheduling.
_Avoid_: Program, split, schedule, workout plan

**Block**:
One ordered unit inside a routine. A **single** exercise, a **superset** (exactly two), or a **circuit** (one or more stations). Owns one warm-up Set Group and one working Set Group shared across the block’s exercises (circuits have no warm-up). Optional setup after the block.
_UI_: Shown as **Exercise** (e.g. “Add exercise”, “Exercise N”); keep Block in code, routes, and this glossary.
_Avoid_: Slot, group, row, exercise entry

**Superset**:
A block with exactly two exercises played as matched rounds: A → transition → B → then working rest. Not a giant set or circuit. Reps-only (no timed prescription).
_Avoid_: Circuit, giant set, pairing (as a noun for the block)

**Circuit**:
A block with one or more stations played in round-based rotation. Dual rest: station rest between stations in a round (hidden when only one station) and round rest after the last station. Stations may use rep or timed prescriptions. No warm-ups, dropsets, profiles, or automatic progression.
_Avoid_: Giant set, complex, AMRAP (as product labels)

**Timed prescription**:
An exercise prescribed by duration in seconds instead of reps (Single or Circuit stations). Play uses a countdown timer. No dropsets, achievement floor, or progression target / bumps while timed. Warm-ups on timed Singles stay rep-based.
_Avoid_: Timer set, hold, isometric (as product labels for the prescription mode)

**Set Group**:
A bag of like sets on a block: either warm-up or working. One set count and one rest for the whole group (for a superset, rest is after the pair; transition sits between A and B). Warm-up steps are each a % of that exercise’s working weight with their own reps; each exercise on the block has its own working weight and prescribed rep target. Individual working-set slots may be **Single** or a **Dropset**.
_Avoid_: Set scheme, wave, drop set (as a name for the Set Group itself)

**Dropset**:
A working-set *slot* with two or more absolute-weight segments that share one reps target. No rest between segments; the working Set Group’s rest runs after the whole slot. Not available on supersets, circuits, or timed Singles. Ignored by progression (achievement floor / bump / carry-forward). “Run the rack” is only an editor helper that fills the segment list.
_Avoid_: Drop set (as a Set Group type), strip set, burn-out set

**Single**:
A working-set *slot* with one weight and one reps **or** duration target (the default opposite of a **Dropset**). Also the name of a one-exercise block type.
_Avoid_: Normal set, straight set (as product labels)

**Working Weight**:
The absolute load prescribed for an exercise on a block’s working sets. The number progression updates. Warm-ups are derived from it. On a Dropset, the first segment may default from working weight, then segments are editable absolutes.
_Avoid_: Top set, training max, TM

**Exercise**:
A named movement in the library. Either shared (master catalog) or owned by a user (custom). No demo media required.
_Avoid_: Movement, lift, catalog exercise (as a separate type)

**Exercise Profile**:
A reusable training recipe for an exercise or block: an exact Target, an effective Floor, working Rest, an ordered warm-up ladder, and deload weight/reps factors. Applying one copies its values into the routine; Target/Floor/rest/warm-ups are not part of Workout history. Deload factors are also copied onto each routine exercise and **auto-pushed** when the profile’s factors change.
_Avoid_: Scheme, programming style

**Preset**:
A system-owned, published Exercise Profile supplied by OVRLOAD. Presets are shown with the `OVRLOAD` prefix and are available to every user.
_Avoid_: Default profile, template (when a user-owned profile is meant)

**Custom Profile**:
A user-owned Exercise Profile that the user can name, edit, archive, restore, and delete when it has no references.
_Avoid_: Personal preset (use Custom Profile)

**User Default Profile**:
The Exercise Profile preselected when the user creates a new Routine. Changing it affects future Routine creation, not existing Routines.
_Avoid_: Global routine profile

**Routine Default Profile**:
The Exercise Profile selected for a Routine and used to seed new blocks. Existing blocks keep their copied values until explicitly updated.
_Avoid_: Live profile link

**Outdated Profile Copy**:
A routine exercise or block that still contains Target/Floor/rest/warm-up values copied from an earlier version of its Exercise Profile. It can be explicitly updated; a Custom override is not updated automatically. Deload weight/reps factors are **not** part of outdated detection — they auto-push on profile save.
_Avoid_: Stale workout, old session

**Workout**:
One started instance of a routine (standard or deload mode). Snapshots the routine’s blocks at start so mid-session and later routine edits don’t rewrite history. At most one in-progress workout per user.
_Avoid_: Session, log, activity

## Progression

**Achievement Floor**:
Minimum reps for a logged set’s weight to count as achieved. Optional; user default with per-exercise override.
_Avoid_: Count reps, achieved-at, valid set threshold

**Progression Target**:
The exercise’s prescribed reps (**Target** in Play). Hitting this many reps at the working weight unlocks a bump suggestion (subject to **Progression Style**). Not a separate setting.
_Avoid_: Bump reps, increase-at, progression threshold

**Progression Style**:
Per-routine setting for how overload ramps within and after a workout; snapshotted onto the Workout at start. **Straight Sets**: same weight for every working set in a block; finish bump if any set hit Target. **Progressive Overload**: when a working set hits Target, raise the next working set by 2.5 kg (Ask on rest or Auto); finish bump only if the **final** working set in the block was at the session’s top weight and hit Target. Training keeps style + mid-block defaults that seed **new routines only** (edits do not rewrite existing routines).
_Avoid_: Bump mode, bump when, progression preset, progression type

**Mid-block bump**:
Within **Progressive Overload**, the +2.5 kg step applied to the **next** working set after Target is hit on the current set. Prefill only — logged weight always wins. Does not update the routine until finish carry-forward / confirmed finish bump.
_Avoid_: Intra-set bump, auto-load

**Carry-forward**:
On finishing (or when re-evaluating an eligible finished workout), set the routine’s working weight for an exercise to the highest achieved top weight from that workout — without asking. Only raises weight; never lowers. Does not apply from deload workouts.
_Avoid_: Sync, catch-up

**Bump**:
A confirmed increase to an exercise’s working weight on the routine, offered when the prescribed Target reps were hit under the Workout’s **Progression Style** finish rule. Never silent. Each confirmation produces a **Bump Record**.
_Avoid_: Increase, PR jump, auto-load

**Bump Record**:
A durable record that a confirmed **Bump** was applied from a specific finished **Workout** to a routine exercise (from→to weight). Source of truth for offering undo when that workout’s working sets are edited and progression is re-evaluated.
_Avoid_: Bump event, progression audit, PR log

**Deload Recipe**:
Per-exercise weight and reps factors (copied from the **Exercise Profile**, auto-pushed when that profile’s factors change) plus per-routine **Deload Velocity**. Factors apply when starting in deload mode; velocity decides when to soft-suggest Deload. Different exercises on the same routine can deload differently via their profiles.
_Avoid_: Recovery recipe, easy recipe

**Deload Velocity**:
How many finished standard workouts on this routine before the dashboard soft-suggests Deload. Part of the **Deload Recipe**, owned by the routine (not the profile). `0` means never suggest. Independent per routine so rares/one-offs can opt out. Training keeps a velocity default for new routines.
_Avoid_: Deload schedule, deload cadence (as a calendar), deload frequency (as weeks)

**Deload Mode**:
A way to start a workout that applies each exercise’s copied deload factors (and the routine’s velocity context) to the snapshot. Omits warm-up Set Groups (and warm-up setup) from the snapshot — deload working weights are already light. Deload workouts do not carry-forward or bump the routine’s usual working weights.
_Avoid_: Easy mode, recovery mode, normal mode (use **standard**)

**Deload Alternate**:
Optional replacement **Exercise** plus its own **Working Weight** on a routine block exercise, used only when starting (or historically logging) in **Deload Mode**. That working weight is used as-is (the exercise’s deload weight factor does not apply). Prescribed reps still come from the primary via that exercise’s reps factor. When set, that exercise’s Deload snapshot is **Singles** only (no **Dropset** segments).
_Avoid_: Swap exercise, deload substitute, recovery lift

## Pauses

**Rest**:
Timed pause after a set (or after the pair in a superset), configured once per Set Group.
_Avoid_: Break, cooldown

**Transition**:
Short pause between the two exercises in a superset round (A then B), before working rest.
_Avoid_: Gap, changeover

**Setup**:
Press-when-done pause for equipment changes. Planned on a block after individual warm-up steps (before the next warm-up rest), after all warm-ups (before working) and/or after the block. Not rest and not working time. Mid warm-up setup runs before that step’s warm-up group rest. Setup-before-working runs before the working Set Group’s rest.
_Avoid_: Transition (for between-block), intermission

**Later** (Do groups later):
Park an untouched Play block (no sets logged yet) so focus advances to a later incomplete block. Parked blocks are skipped until the end/Finish offer: do them now (unpark all) or decline (clear parked marks; incompletes stay). Distinct from **Skip rest of group** / **Skip group**, which delete incompletes.
_Avoid_: Defer, come back later (as a noun), skip for now

## Loading

**Plate Profile**:
A user’s bar-loading setup: bar presets, plate denominations with counts, and colour coding. Used by the plate calculator; if a target isn’t loadable, offer the nearest loadable weight.
_Avoid_: Gym inventory, plate set
