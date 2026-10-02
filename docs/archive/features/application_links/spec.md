> Status: ARCHIVED (implemented). Historical record only. The code is the source of truth. DO NOT use this document as a reference to implement or change code.

# application_links — links field on application profiles

> **Kind:** backend + Profiles screen.
> **How to run:** `/plan-spec docs/features/application_links/spec.md`,
> then `/execute-phases`. No new dependencies.

## Part 0 — Context and decisions (owner, final)

Application profiles already have a **cover letter** field, which is inserted
into the email body through the `{{ cover_letter }}` template variable. This
feature adds a **links** field to the profile, inserted the same way through a
new `{{ links }}` variable.

1. The links field lives on the **application profile** (the same place as the
   cover letter), so each profile (language) has its own links.
2. A profile can hold **many links**. Each link has two inputs:
    - **label** — free text shown to the recruiter (e.g. `Linkedin`).
    - **url** — the link itself (e.g. `https://...`).
3. Adding `{{ links }}` to the email body renders every link as one line,
   in the format `<label> : <url>`:

    ```text
    Linkedin : https://asdasdasdsdsds
    Portifolio : https://asdasdasasds
    ```

4. Same variable mechanics as `{{ cover_letter }}`: the variable is replaced
   wherever it appears in the email body template.

## Part B — Product spec

### B.1 Links field (Profiles)

- The profile form gets a **Links** field: a repeatable list of rows, each with
  a **label** input and a **url** input, plus add/remove row controls.
- The field is placed **right below the cover letter** field and uses the
  **same design system** as the rest of the profile form (same field wrapper,
  label, input, button and spacing patterns already used there).
- Rows are stored on the application profile and kept in the order the client
  entered them.

### B.2 `{{ links }}` template variable

- New template variable `{{ links }}`, available wherever `{{ cover_letter }}`
  is available (email body template, template preview).
- Output: one line per link, `label : url`, separated by line breaks.
