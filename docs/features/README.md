# Feature documentation

One directory per feature, with no content duplicated between files:

```text
docs/features/<feature>/
├── spec.md     # required — living product doc of the feature
├── plan.md?    # transient — phases from /plan-feature, run with /exec-phase
└── assets/?    # attachments (screenshots, mockups, PDFs) linked from spec.md
```

Execution state produced by the `execute-feature` skill outside a plan goes
to `.claude/state/<feature>.md` (git-ignored).

## The spec

`spec.md` is permanent: it stays after the feature ships and keeps describing
it. It has two parts that never mix:

-   **Body** — what the feature does today. For a new feature, "Requirements"
    is the target until it is implemented.
-   **`## Pending changes`** — what the owner wants to change in an existing
    feature and is not implemented yet. It is the target of `/plan-feature`, and
    it empties as phases ship.

Requirements and pending changes are numbered (`R1`…, `P1`…) and never
renumbered, so plans can cite them. Attachments live in `assets/` and are
linked with relative paths.

## Flow

| Situation                     | Commands                                                                                                                                                    |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| New feature                   | `/create-feature-spec` → `/plan-feature` → `/exec-phase` …                                                                                                  |
| Existing feature, no spec yet | `/create-feature-spec` (skeleton) → `/update-feature-spec` (fills from code) → `/create-feature-spec` (pending changes) → `/plan-feature` → `/exec-phase` … |
| Change an existing feature    | `/create-feature-spec` (adds to `## Pending changes`) → `/plan-feature` → `/exec-phase` …                                                                   |
| Spec drifted from the code    | `/update-feature-spec`                                                                                                                                      |

-   `/create-feature-spec <feature>` — opens the spec and collects items from
    the owner, one by one, until they say they are done. Doesn't read code.
-   `/update-feature-spec <feature>` — reconciles the spec with the code (code
    wins); folds pending changes the code already does into the body.
-   `/plan-feature <feature>` — diagnoses spec x code (implemented / partial /
    divergent / missing / unverifiable), asks the owner what changes the plan,
    records decisions in the spec, and writes `plan.md` with small,
    self-contained phases (each with its `Origin`).
-   `/exec-phase <feature> <phases>` — runs the listed phases unattended
    through the `execute-feature` orchestrator, with one commit + push per
    phase (trailer `Spec: docs/features/<feature>`). After the last phase it
    folds the pending changes the plan covered into the spec body.

## Lifecycle

-   When every phase of a plan is `DONE`, the owner deletes `plan.md`. The
    agents never move or delete spec/plan files.
-   Read only the spec of the feature in progress, never this whole folder.

## Source of truth (highest to lowest)

1. The owner's explicit instructions in the current conversation
2. The current code (including manual changes)
3. The spec body of the feature in progress

`## Pending changes` (and "Requirements" of a feature not yet built) is not a
claim about the code; it is the owner's recorded intent and the target of the
plan. Where the spec body and the code disagree, the code wins: never revert
code to match the body; run `/update-feature-spec`, or let the owner decide
in `/plan-feature` that the code should change.
