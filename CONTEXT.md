# OVRLOAD

Personal strength training for serious lifters: plan routines, log sessions, track progressive overload. Not coaching. Product branding (name, mark, visual direction) lives in `docs/branding.md`.

## Language

**User**:
The account holder who owns routines, workouts, customs, and a plate profile.
_Avoid_: Athlete, lifter, member

**Routine**:
A named plan the user owns and can start as a workout. Ordered list of blocks. Flat — no calendar or “today’s workout” scheduling.
_Avoid_: Program, split, schedule, workout plan

**Block**:
One ordered unit inside a routine. Either a single exercise or a **superset** (exactly two exercises). Owns one warm-up Set Group and one working Set Group shared across the block’s exercises. Optional setup after the block.
_UI_: Shown as **Exercise** (e.g. “Add exercise”, “Exercise N”); keep Block in code, routes, and this glossary.
_Avoid_: Slot, group, row, exercise entry

**Superset**:
A block with exactly two exercises played as matched rounds: A → transition → B → then working rest. Not a giant set or circuit.
_Avoid_: Circuit, giant set, pairing (as a noun for the block)

**Set Group**:
A bag of like sets on a block: either warm-up or working. One set count and one rest for the whole group (for a superset, rest is after the pair; transition sits between A and B). Warm-up steps are each a % of that exercise’s working weight with their own reps; each exercise on the block has its own working weight and prescribed rep target. Individual working-set slots may be **Single** or a **Dropset**.
_Avoid_: Set scheme, wave, drop set (as a name for the Set Group itself)

**Dropset**:
A working-set *slot* with two or more absolute-weight segments that share one reps target. No rest between segments; the working Set Group’s rest runs after the whole slot. Not available on supersets. Ignored by progression (achievement floor / bump / carry-forward). “Run the rack” is only an editor helper that fills the segment list.
_Avoid_: Drop set (as a Set Group type), strip set, burn-out set

**Single**:
A working-set *slot* with one weight and one reps target (the default opposite of a **Dropset**).
_Avoid_: Normal set, straight set (as product labels)

**Working Weight**:
The absolute load prescribed for an exercise on a block’s working sets. The number progression updates. Warm-ups are derived from it. On a Dropset, the first segment may default from working weight, then segments are editable absolutes.
_Avoid_: Top set, training max, TM

**Exercise**:
A named movement in the library. Either shared (master catalog) or owned by a user (custom). No demo media required.
_Avoid_: Movement, lift, catalog exercise (as a separate type)

**Workout**:
One started instance of a routine (standard or deload mode). Snapshots the routine’s blocks at start so mid-session and later routine edits don’t rewrite history. At most one in-progress workout per user.
_Avoid_: Session, log, activity

## Progression

**Achievement Floor**:
Minimum reps for a logged set’s weight to count as achieved. Optional; user default with per-exercise override.
_Avoid_: Count reps, achieved-at, valid set threshold

**Progression Target**:
The exercise’s prescribed reps (**Target** in Play). Hitting this many reps at the working weight unlocks a bump suggestion (subject to **Bump When**). Not a separate setting.
_Avoid_: Bump reps, increase-at, progression threshold

**Bump When**:
Which completed working set(s) decide whether the Progression Target was hit for a bump suggestion. User default, snapshotted onto the Workout at start. **Any set**: any working set at or above the snapshotted working weight. **Last set at top weight**: among sets at the session’s heaviest weight, the last one (must still be at or above the snapshotted working weight).
_Avoid_: Bump mode, progression style, confirm rule

**Carry-forward**:
On finishing (or when re-evaluating an eligible finished workout), set the routine’s working weight for an exercise to the highest achieved top weight from that workout — without asking. Only raises weight; never lowers. Does not apply from deload workouts.
_Avoid_: Sync, catch-up

**Bump**:
A confirmed increase to an exercise’s working weight on the routine, offered when the prescribed Target reps were hit under the Workout’s **Bump When** rule. Never silent. Each confirmation produces a **Bump Record**.
_Avoid_: Increase, PR jump, auto-load

**Bump Record**:
A durable record that a confirmed **Bump** was applied from a specific finished **Workout** to a routine exercise (from→to weight). Source of truth for offering undo when that workout’s working sets are edited and progression is re-evaluated.
_Avoid_: Bump event, progression audit, PR log

**Deload Recipe**:
Per-routine uniform factors (weight and reps) plus **Deload Velocity**, applied when starting in deload mode / deciding when to soft-suggest Deload. Same factors for every exercise on the routine. New routines seed from the user’s Training deload defaults.
_Avoid_: Recovery recipe, easy recipe

**Deload Velocity**:
How many finished standard workouts on this routine before the dashboard soft-suggests Deload. Part of the **Deload Recipe**. `0` means never suggest. Independent per routine so rares/one-offs can opt out.
_Avoid_: Deload schedule, deload cadence (as a calendar), deload frequency (as weeks)

**Deload Mode**:
A way to start a workout that applies the routine’s deload recipe to the snapshot. Omits warm-up Set Groups (and warm-up setup) from the snapshot — deload working weights are already light. Deload workouts do not carry-forward or bump the routine’s usual working weights.
_Avoid_: Easy mode, recovery mode, normal mode (use **standard**)

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

## Loading

**Plate Profile**:
A user’s bar-loading setup: bar presets, plate denominations with counts, and colour coding. Used by the plate calculator; if a target isn’t loadable, offer the nearest loadable weight.
_Avoid_: Gym inventory, plate set
