---
description: Spatie Laravel Data DTOs for HTTP request/response boundaries
paths:
  - "app/**/Data/**/*.php"
  - "app/**/Http/**/*.php"
alwaysApply: false
---

# Spatie Laravel Data (HTTP boundaries)

Prefer Spatie Data under `App\{Domain}\Data\...` over `$request->validate()` or ad-hoc Inertia prop arrays. Controllers type-hint Data for writes; build page/JSON bags from Data for reads. Shared casts: `App\Shared\Data\Casts`.

- Constructor props: always `public readonly`.
- Prefer PHP types (and PHPDoc list shapes) over redundant `#[Present]` / `#[ArrayType]`.
- Optional empty lists: default `= []` and **omit the key** when empty (inferred `required` rejects `[]`).
- Validation attributes only for constraints types cannot express (`#[Min]`, `#[Max]`, `#[Exists]`, …). Prefer attributes over `rules()`; reserve `rules()` for nested/`*` cases.

Full decision: `docs/adr/0001-spatie-laravel-data-http-boundaries.md`.
