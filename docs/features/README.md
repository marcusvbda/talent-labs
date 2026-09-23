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
