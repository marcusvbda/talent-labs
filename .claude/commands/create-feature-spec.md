---
description: Create docs/features/<feature>/spec.md (or open the existing one) and stay in the thread folding each item the owner sends into it, attachments included (assets/)
argument-hint: <feature-slug>
model: claude-sonnet-5-5
---

# /create-feature-spec $ARGUMENTS

Takes the feature name in `$ARGUMENTS`, in kebab-case (e.g.
`/create-feature-spec saved-searches`). If it is empty, ask for the name. If it
is not kebab-case, normalize it and confirm with the owner.

This command only writes the spec. Do not implement anything, do not change
code and do not create a plan (that comes later, via `/plan-feature $ARGUMENTS`).

## Spec convention

The spec describes the feature's behaviour. There are two cases:

-   **New feature** (nothing implemented): everything the owner defines goes
    into "Requirements". It is the target of `/plan-feature`.
-   **Existing feature** (already in the code): the spec body describes the
    **current state** (filled from the code by `/update-feature-spec`), and what
    the owner wants to **change** goes **only** into the `## Pending changes`
    section. That way the spec never mixes "what the code does today" with "what
    it is going to do". `/plan-feature` uses that section as its main target.
    When every plan phase that came from it is done, `/exec-phase` folds those
    changes into the body as implemented behaviour and removes them from the
    section (`/update-feature-spec` remains available to reconcile the spec with
    the code afterwards).

## Steps

1. Check `docs/features/$ARGUMENTS/`.
    - If `spec.md` already exists with content, don't overwrite or recreate it:
      tell the owner and go straight into **collection mode**. If the spec has a
      `## Pending changes` section or describes the current state of the code,
      new items go there (create the section if missing); if it is the spec of a
      new feature not yet implemented, they go into "Requirements".
    - If it exists but only holds the empty skeleton, treat it as missing
      (step 2), without recreating the file.
2. Ask the owner: **is the feature new, or does it already exist in the code?**

    - **New:** create `docs/features/$ARGUMENTS/spec.md` (if it doesn't exist
      yet) with the skeleton below and go to step 4.
    - **Already in the code, no spec:** create `spec.md` (if it doesn't exist
      yet) with the skeleton below and go to step 3.

    ```markdown
    # <Feature name>

    ## 1. What it is

    ## 2. Requirements

    ## 3. Attachments and references

    ## 4. Open points
    ```

    Don't fill in content the owner didn't give.

3. (Existing feature without a filled spec only.) The spec must describe the
   current state before it receives changes. Tell the owner to run
   `/update-feature-spec $ARGUMENTS` (it fills the spec from the code) and then
   run `/create-feature-spec $ARGUMENTS` again to define the changes. Stop
   here. Don't fill the body yourself: the source of truth for the current
   state is the code, not the owner's memory.
4. Tell the owner the spec is ready and you are waiting for items. From here
   on you are in **collection mode**: every owner message is an item to add to
   the spec.

## Collection mode

For each item received:

1. Write the item into `spec.md`: into "Requirements" if the feature is new,
   or into `## Pending changes` if it exists (never edit the body that
   describes the current state, unless the owner says it is wrong). Rewrite
   only what's needed to fit it in; preserve what the owner already gave. Use
   the owner's wording, in English (translate faithfully if they write in
   another language), without inventing requirements, file names, routes or
   behaviour they didn't state. Number requirements and pending changes
   (`R1`, `R2`… / `P1`, `P2`…) so the plan can cite them; never renumber, and
   never reuse a removed number.
2. If the item is ambiguous, contradicts something already written, or leaves
   an obvious gap (empty state, error, permission, who sees it, mobile/theme,
   EN/PT copy), **don't assume**: record it in "Open points" and, if it
   matters, ask the owner a short question (`AskUserQuestion` with the
   recommended option first when there are real options).
3. Flag in "Open points", without deciding, anything that would need: a new
   composer/npm dependency, a destructive data operation, polling instead of
   realtime, Spanish copy (the product is EN/PT only), or a pricing/plan
   change.
4. Reply with a one- or two-line confirmation: where the item went (section)
   and, if any, the pending question. Don't repeat the whole spec.
5. Wait for the next item. Don't close the session yourself and don't propose
   an implementation.

If the owner asks to reorganize, remove or edit something already written, do
only that.

## Attachments

When the owner sends an attachment (image, PDF, screenshot, file) or points to
a local file by path:

1. Copy the file to `docs/features/$ARGUMENTS/assets/` (create the folder if
   missing). If the attachment only exists inside the conversation and has no
   path on disk, save it there; if that's not possible, tell the owner and ask
   for the file path.
2. Use a descriptive kebab-case name, keeping the extension (e.g.
   `filters-modal.png`). On a name clash, don't overwrite; add a numeric
   suffix.
3. Link it in the spec with a relative path, next to the item it belongs to,
   and also list it in "Attachments and references":

    ```markdown
    ![Filters modal](assets/filters-modal.png)
    [Scoring rules](assets/scoring-rules.pdf)
    ```

    Images use `![description](assets/file)`; other files use
    `[description](assets/file)`.

4. Describe in one line, in the spec, what the attachment shows or defines, so
   `/plan-feature` has context. For an image, look at it and describe only
   what actually appears. If you don't understand the attachment's role in the
   item, ask.
5. External links (Figma, Notion, tickets) go straight into "Attachments and
   references", with one line of context. Don't download or read external
   content unless the owner asks.

## Closing

Close only when the owner says they're done ("done", "that's it", "close it").
Then:

1. Re-read `spec.md` once, check that every file in `assets/` is linked and
   that the relative links resolve.
2. Reply with a short summary: sections filled, attachments added and open
   points left.
3. Say the next step is `/plan-feature $ARGUMENTS` (in a fresh thread). Don't
   run it.

## Rules

-   Only create/edit files inside `docs/features/$ARGUMENTS/`.
-   Never run git writes (`CLAUDE.md`).
-   Don't read `.env` files.
-   Don't read or analyse the code at this stage unless the owner asks; the spec
    describes what the feature must be, and the comparison with the code belongs
    to `/plan-feature`.
-   Read only this feature's folder, never the whole `docs/features/`.
