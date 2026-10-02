# Feature documentation

All project documentation lives here, one directory per feature, with no
content duplicated between files:

```text
docs/features/<feature>/
├── spec.md          # required — product source of truth, with acceptance criteria
├── tech-design.md?  # optional — binding technical design when present
└── plan.md?         # optional — phased execution plan from /plan-spec, run with /execute-phases
```

`spec.md` is never deleted or edited to match code. `plan.md` references spec
sections instead of copying them, except for the per-phase contracts it
embeds for execution. Execution state produced by the `execute-feature` skill
goes to `.claude/state/<feature>.md` (git-ignored).

## Lifecycle

`docs/features/<feature>/` holds ACTIVE work only (spec + plan). When a feature
is finished it moves to `docs/archive/features/<feature>/` (active → archived).
`docs/archive/` is history: never read, search or use it unless the owner
explicitly asks. Read only the spec of the feature in progress, never this
whole folder.

## Source of truth (highest to lowest)

1. The owner's explicit instructions in the current conversation
2. The current code (including manual changes)
3. The active spec of the feature in progress
4. Archived specs (never consulted)

A spec is intent at the time it was written; code is current reality, so on
conflict the code wins. Never change code just to match a spec. Report the
divergence and propose a spec update (`Deviation:` note or section rewrite).
If code is changed by hand, update the active spec or add a
`Deviation: <what changed and why>` note.

## Completion checklist (when ALL tasks in a plan are done)

1. `git mv` the feature folder to `docs/archive/features/<feature>/`.
2. Add the header "Status: IMPLEMENTED on YYYY-MM-DD. Historical record only.
   The code is the source of truth." to the spec.
3. Delete the plan file.
4. If the feature needs permanent documentation, create/update a short summary
   of current state and key decisions in `docs/architecture/<area>.md`.
