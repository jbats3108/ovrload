# Routine editor layout and styling

Desktop uses a dense all-blocks list; mobile uses a one-block stage. Visual styling is dark zinc with lime accents (from the B prototype). Chosen after a throwaway UI prototype; the prototype route was removed once folded into `resources/js/pages/routines/Edit.vue`.

## Mobile density

Mobile stage tabs start with a **Routine** sheet (name, routine profile, Deload multipliers), then one tab per exercise. Profile-owned Target, Floor, Rest, warm-up, and Deload Alternate stay collapsed on exercise sheets until Custom.

## Desktop density

Desktop keeps the all-blocks table with separate **Rest** and **Warm-up** columns. Routine profile and Deload live in a collapsed **Routine settings** strip under the title (same fields as the mobile Routine sheet). Profile-owned Target / Floor and shared Rest / Warm-up collapse to summary + Customise (same helpers as mobile). Warm-up summaries list each step on its own line; Custom with no steps shows **Enable warm-up** instead of the full editor. Each non-superset block has its own collapsed **Dropsets** row under that block. Deload alternate is always available per exercise (not gated by Custom). Setup labels use full words.
