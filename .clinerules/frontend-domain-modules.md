---
description: Frontend domain modules mirroring PHP App domains
paths:
  - "resources/js/**/*.{ts,vue,tsx,js}"
alwaysApply: false
---

# Frontend domain modules

Inertia pages under `resources/js/pages/` stay thin entrypoints. Domain types, `lib/`, composables, and presentational SFCs live under `resources/js/{domain}/` (e.g. `routines/`, `workouts/`), mirroring `App\{Domain}`.

- App chrome types: `resources/js/types/`; UI kit / shell: `resources/js/components/`.
- Cross-domain pieces: `resources/js/shared/` when introduced.
- Page session state: create + provide/inject composable stores (not Pinia, not module singletons) — e.g. `useRoutineEditor` / `useWorkoutPlayer`.
- Pure transforms: `{domain}/lib/`.

## Vue props defaults

Do **not** destructure `withDefaults(defineProps(...), …)`. That disables reactive destructure (Vite warning).

Prefer destructure defaults:

```ts
const { variant = 'desktop' } = defineProps<{ variant?: EditorDensity }>();
```

Or keep a non-destructured props object:

```ts
const props = withDefaults(defineProps<{ variant?: EditorDensity }>(), {
    variant: 'desktop',
});
```

Full decision: `docs/adr/0005-frontend-domain-modules.md`.
