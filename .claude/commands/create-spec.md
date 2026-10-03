---
description: Open a spec session for docs/features/<feature>/spec.md. Creates the spec if it doesn't exist (or loads the existing one), then stays in the thread folding each item the owner sends into the spec, until the owner clears the thread.
argument-hint: <feature-slug> [first item, rough notes or a path to a notes file]
---

# Spec session

Feature slug: `$1`
Owner's first item (optional): $ARGUMENTS

This command opens a **persistent spec session**. After it runs, the thread
stays in this mode: the owner sends what the spec must include, item by item,
over as many prompts as they want, and you fold each one into
`docs/features/$1/spec.md`. The session ends only when the owner clears the
thread or runs another command. Never end it yourself, never "wrap up", and
never start planning.

You are **specifying only**. Do not implement anything, do not install anything,
do not run migrations or seeders, do not write tests, and never run git writes
(`CLAUDE.md`). The only files you write are `docs/features/$1/spec.md` and the
images the owner sends, saved under `docs/features/$1/images/` (folders are
created if missing). Do not create `plan.md` — that is `/plan-spec`'s
job, run by the owner afterwards.

A spec is **product truth**: it says what the feature does and how we know it is
done. It is not a plan and not code. Keep technical detail to what the product
needs to be unambiguous (data model, rules, copy), and leave sequencing to
`/plan-spec`.

## 1. Open the session

- `$1` must be a kebab-case feature slug. If the first argument looks like a
  sentence or a path, derive a slug from it and state it before continuing.
- Target: `docs/features/$1/spec.md`.
- Docs live only in `docs/features/<feature>/`. Never read `docs/archive/`
  unless the owner asks, and never read other features' folders. If an item
  points to an archived or sibling feature, ask the owner for the relevant
  facts instead.
- **Spec does not exist → create it now**, immediately, as an **empty file**
  (zero content: no title, no template, no placeholders; this saves tokens).
  Do this before any audit or question, so the file exists for the whole
  session. The first item that is folded in writes the header (`# <title
derived from the slug>` and `Status: DRAFT · Created: <today>`) and only the
  sections that item touches, using the headings and numbering of section 4.
  Later items add the remaining sections when they first need them, in
  template order. Never write `_Not yet specified._` or any other placeholder.
- **Spec exists → do not recreate or overwrite it.** Read it in full and treat
  it as the active spec of the thread. Summarise it in a few lines (goal,
  number of ACs, open decisions, sections not yet present).
- If the optional first item is a file path, read that file and treat its
  content as the owner's first item; otherwise, if it is text, treat the text
  as the first item and handle it as in section 2.
- Then **stop and wait**. End your message by saying the spec is open and you
  are ready for items. Do not interview the owner up front.

## 2. Handle each item

Every owner message after the session opens is one or more items for the
spec, unless they clearly say otherwise (a question, a request to review,
"finalize"). For each item:

1. **Audit only what it touches** (read-only, proportionate). Load the
   `job-collection` skill if it touches sources, adapters, collection runs,
   job postings or "today's jobs". Check existing models, enums, resources,
   pages, routes, config (`config/talent.php`) and translations with Boost
   `database-schema` and `search-docs` as needed. The current code wins over
   recollection: if the item conflicts with how the code works today, say so
   in your reply; don't silently pick a side.
2. **Fold it into the spec** right away, in the sections where it belongs:
   rules into Ground rules, columns/enums into Data model, steps and unhappy
   paths into Flows, copy and states into UI surfaces, and at least one
   testable AC per new rule or flow. Add a section only when the item first
   fills it (keep template order and numbering). Use targeted edits, not a
   full rewrite.
3. **Ask only what blocks you**: a product decision the owner must make and
   the code can't answer (behaviour, scope boundary, copy, who sees what,
   plan/paywall rules, edge cases with more than one reasonable outcome). Ask
   briefly in chat (use `AskUserQuestion` with a recommended option first when
   there are real options). Don't ask about things you can verify in the repo
   or that have a conventional default — pick the default and record it under
   Assumptions. If the owner leaves a question unanswered, record it under
   Open decisions and keep going.
4. **Screenshots and images**: when the owner sends an image (print, mockup,
   screenshot), save it as a file in `docs/features/$1/images/` (create the
   folder if missing; use a short descriptive kebab-case name, e.g.
   `review-modal-footer.png`; never overwrite an existing file, add a numeric
   suffix instead). Link it in the spec with a relative path
   (`![<what it shows>](images/<file>)`) in the section it informs (usually
   UI surfaces), with one line saying what the image shows and what in it is
   binding, so `/plan-spec` and the executors have the visual context. If the
   image data is not available as a file you can copy, say so and ask the
   owner for the file path instead of describing it from memory.
5. **Reply short**: which sections you changed, any assumption you made, any
   conflict with current code, and any question. No re-printing of the spec.

Rules for editing across a long session:

- The owner's latest instruction wins. If a new item contradicts something
  already in the spec, update the spec to the new instruction and mention what
  changed.
- Keep ids stable: R1…, AC1…, OD1… are never renumbered. A removed item is
  deleted and its number is not reused; new ones take the next number.
- Everything the owner sends goes into the spec only if they asked for it
  (scope discipline). Extra ideas you notice go under **Ideas not included**,
  not into the requirements.
- Never make these choices silently — they become Open decisions unless the
  owner resolves them: new composer/npm dependencies, destructive data
  operations, pricing or plan changes, anything that contradicts current code.
- The spec on disk is the memory of the session. Save after every item, so
  nothing is lost when the owner clears the thread.

Constraints the spec must respect:

- Everything in English (UI copy keys and defaults included); the product is
  EN/PT only, so no Spanish.
- Filament surfaces update via realtime (`Table::socket()`, the realtime
  listener), never polling.
- During development, schema changes are folded into the table's create
  migration (owner runs `migrate:fresh`) — the spec states the final schema,
  not an "add column" migration history.
- On a divergence between the spec and current code, don't rewrite the spec to
  match code (`CLAUDE.md`): report it and propose a `Deviation:` note.

## 3. When the owner asks to review or finalize

Only when asked ("review", "finalize", "is it ready?"):

- List sections missing from the spec, ACs missing for a rule or flow,
  contradictions, vague ACs, and open decisions.
- Add genuinely empty sections with `None.` only with the owner's go-ahead.
- When every template section is present and none is unfinished, set
  `Status: READY`. `/plan-spec` refuses specs with placeholders, missing
  sections or unfinished sections.
- Name the next step, exactly: `/plan-spec docs/features/$1/spec.md`. Do not
  start planning.

## 4. Template

```markdown
# <Feature title>

Status: DRAFT · Created: <YYYY-MM-DD>

## 1. Goal

<What problem this solves, for whom, and the outcome. 2–5 sentences.>

## 2. Ground rules

<Product rules and invariants that hold across the whole feature. Numbered, so
other sections and the plan can cite them (R1, R2, …).>

## 3. Data model

<Final schema: tables, columns with types and nullability, enums with every
value, relationships, indexes/uniqueness. Say which parts already exist and
which are new. "None." if no data changes.>

## 4. Flows

<Each user/system flow step by step, including the unhappy paths and empty
states. Name the actor (job seeker, admin, scheduler/queue).>

## 5. UI surfaces

<Each screen, panel, action or notification touched: where it lives (Filament
vs Inertia/React), what it shows, states, and the exact UI copy in English
(with the PT translation when the surface is localized).>

## 6. Acceptance criteria

<Numbered AC1, AC2, … Each one a single observable, testable statement of
behaviour ("Given … when … then …"). Together they cover every rule and flow.>

## 7. Verification

<How to confirm it works: the deterministic checks that exist in this repo
(from `composer.json` / `package.json`), smoke tests, and the owner's manual
checklist. Only commands that really exist.>

## 8. Out of scope

<What this feature deliberately does not do.>

## 9. Assumptions

<Defaults chosen without asking, each with a one-line reason.>

## 10. Open decisions

<Questions still unresolved, each as `OD1 — question · options · recommendation`.
"None." when everything is resolved.>

## Ideas not included

<Extra ideas noticed along the way. Not requirements. "None." if empty.>
```

Quality bar for what you write into the spec:

- ACs are testable and unambiguous; no "should be fast/nice/intuitive".
- Names (columns, enums, routes, copy) match the existing code's conventions.
- No contradiction between sections, and none with the current code. Where
  the spec intentionally changes current behaviour, say so explicitly.
