---
description: Start implementation work on a new branch from main when currently on main
alwaysApply: true
---

# Git: branch before work

When an agent chat starts **implementation work** (code, tests, docs that will be committed) and the current branch is `main`:

1. Create and check out a new branch from up-to-date `main` before editing files.
2. Name it for the task (`feature/…`, `fix/…`, or similar). Prefer `git fetch origin` then branch from `origin/main` when remote is available.
3. Do **not** commit directly on `main`.

Skip creating a branch when:

- Already on a non-`main` feature/fix branch for this work
- The user explicitly asks to stay on `main` or names a different base/branch
- The task is read-only (explore, answer, review with no repo writes)

If the working tree on `main` has unrelated local changes, stop and ask before branching or stashing.
