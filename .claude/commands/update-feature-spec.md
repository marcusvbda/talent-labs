---
description: Compare docs/features/<feature>/spec.md with the current code and update the spec, using the code as the source of truth
argument-hint: <feature-slug>
model: claude-sonnet-5-5
---

# /update-feature-spec $ARGUMENTS

Takes the feature name (folder in `docs/features/`) in `$ARGUMENTS`, e.g.
`saved-searches`. If it is empty, list the folders in `docs/features/` and ask
which one.

The spec body describes the current state of the system. Here the **code is
the source of truth**: when spec and code diverge, the spec is what changes.
Don't change code.

## Steps

1. Read `docs/features/$ARGUMENTS/spec.md` in full. If it doesn't exist, stop
   and ask the owner to run `/create-feature-spec $ARGUMENTS` first (this
   command only updates an existing spec). If the spec only holds the empty
   skeleton (created by `/create-feature-spec` for a feature that already
   exists in the code), use **fill** mode: instead of comparing, find the
   feature in the code (grep for the feature name, routes, controllers,
   Filament resources, Inertia pages, models, jobs, translations) and write the
   current state into the existing sections, creating the sections needed.
   Ask the owner whether the identification of the feature in the code (files
   and routes) is right before writing.
    - **Pending changes:** if the spec has a `## Pending changes` section, it
      describes what the owner **wants** to change, not the current state.
      Never treat it as "wrong" or delete it for diverging from the code. For
      each item: if the code already does what it describes, fold it into the
      body (in the right section) and remove it from the section; if the code
      does only part of it, keep only the missing part; if it does none of it,
      keep the item as is. If the section ends up empty, remove it. Items the
      code denies or contradicts go into the report as "pending" or "diverges
      from the desired change".
2. Extract the verifiable claims from the spec: file paths, class/method/
   component/route/middleware/column names, enum values, HTTP statuses and
   messages, constants and config keys (`config/talent.php`), UI copy and
   translation keys, per-surface behaviour (Filament vs Inertia), realtime
   channels/events, invariants and the list of tests.
3. Check each one against the code. Load the `job-collection` skill if the
   feature touches sources, adapters, collection runs, job postings or
   "today's jobs". Use Boost `database-schema` for tables and columns. Use
   `git log` and `git diff` on the files the spec cites only to find what
   changed since the spec's last edit; the conclusion always comes from
   reading the current code.
4. Look for what the spec **doesn't** cover: read the feature's files (grep for
   the spec's key terms, routes, components, models) and identify behaviour,
   route, screen or test that is not documented.
5. Classify each divergence:
    - **Wrong:** the spec says X, the code does Y.
    - **Obsolete:** the spec cites something that no longer exists (file,
      route, renamed/removed column).
    - **Missing:** the code does something the spec doesn't describe.
    - **Unverifiable:** couldn't be confirmed (explain why). Don't invent; keep
      the original text and mark it.
6. For large work (a spec covering many areas), you may split the
   verification across read-only `Explore` agents in parallel, one per area
   (e.g. backend, Filament, Inertia). They only read and report; editing the
   spec is yours.
7. Update `docs/features/$ARGUMENTS/spec.md` with surgical edits, only in the
   divergent parts:
    - keep structure, section numbering, requirement ids, language (English)
      and tone;
    - fix exact values and names, remove what's obsolete, add what was missing
      in the right section (create a section only if there's no place for it);
    - don't rewrite correct passages or "improve" the text;
    - keep "Open points" current: resolved items leave, new ones come in.
8. Validate the result: re-read the edited parts against the code one last
   time.

## Reply to the owner

End with a short summary:

-   how many divergences per category (wrong, obsolete, missing, unverifiable);
-   an objective list of what changed in the spec (`section: before -> after`);
-   unverifiable items and what's needed to verify them;
-   if nothing diverged, say so and don't edit the file.

## Rules

-   Never run git writes (`CLAUDE.md`). Leave the change in the working tree.
-   Don't read `.env` files.
-   Only edit `docs/features/$ARGUMENTS/spec.md` (never `plan.md`). If you spot a
    bug in the code, report it to the owner; don't fix it and don't record it as
    "expected behaviour" without flagging that it looks like a bug.
-   If the code contradicts an invariant of the spec, treat it as a spec
    divergence **and** point out to the owner that the invariant was broken.
